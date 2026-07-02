<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Vue\Expr\Parser;
use JesseGall\CodeCommandments\Vue\Ts\Node\CallExpr;
use JesseGall\CodeCommandments\Vue\Ts\Node\FunctionType;
use JesseGall\CodeCommandments\Vue\Ts\Node\ImportDecl;
use JesseGall\CodeCommandments\Vue\Ts\Node\KeywordType;
use JesseGall\CodeCommandments\Vue\Ts\Node\Module;
use JesseGall\CodeCommandments\Vue\Ts\Node\NamedType;
use JesseGall\CodeCommandments\Vue\Ts\Node\NamePattern;
use JesseGall\CodeCommandments\Vue\Ts\Node\ObjectPattern;
use JesseGall\CodeCommandments\Vue\Ts\Node\ObjectType;
use JesseGall\CodeCommandments\Vue\Ts\Node\VariableDecl;
use JesseGall\CodeCommandments\Vue\Ts\Parser as TsParser;

/**
 * A `<script setup>` block read structurally — the third parser in the engine, beside
 * the template {@see Tokenizer} and the expression {@see Expr\Parser}. It lexes the
 * script into tokens (comments and string bodies skipped, so `from` inside a string
 * never confuses it) and reads the two things a scribe needs off that token stream,
 * never a regex over the text:
 *
 *   - {@see imports} — each import's bound names and its verbatim statement, so an
 *     extracted component can carry the children/helpers/types it actually uses;
 *   - {@see propTypes} — the `defineProps<{ … }>()` field types, so a forwarded prop
 *     keeps its real type instead of `unknown`.
 */
final class Script
{
    /** @var list<array{kind: string, value: string, start: int, end: int}> */
    private array $tokens;

    /** Reactive wrappers whose value type is what the template sees — unwrapped. */
    private const array REACTIVE = ['ref', 'computed', 'shallowRef', 'toRef', 'customRef', 'reactive'];

    private ?Module $ast = null;

    public function __construct(private readonly string $source)
    {
        $this->tokens = $this->lex($source);
    }

    /**
     * The parsed syntax tree of this script — the {@see Module} the type-reading methods query
     * instead of scanning tokens. Parsed once, lazily.
     */
    private function ast(): Module
    {
        return $this->ast ??= TsParser::module($this->source);
    }

    /**
     * The type a reactive initializer exposes to the template — `ref<T>()`/`computed<T>()` → `T`
     * (unwrapped), `ref(false)` → `boolean` (from the literal), `computed(() => a === b)` →
     * `boolean` (from the expression). Null when the call isn't a reactive wrapper or can't be read.
     */
    private function reactiveType(CallExpr $call): ?string
    {
        if (! in_array($call->callee, self::REACTIVE, true)) {
            return null;
        }

        if (($argument = $call->firstTypeArgument()) !== null) {
            return $argument->render();
        }

        $expression = $call->arguments[0] ?? null;

        if ($expression === null) {
            return null;
        }

        $parsed = Parser::parse($expression);

        return $call->callee === 'computed' ? $parsed->returnType() : $parsed->inferType();
    }

    /**
     * Every import, with the names it binds and its exact source text.
     *
     * @return list<array{names: list<string>, statement: string}>
     */
    public function imports(): array
    {
        return array_map(static function (ImportDecl $import): array {
            $statement = $import->render();

            return [
                'names' => array_keys($import->bindings),
                'statement' => str_ends_with($statement, ';') ? $statement : "{$statement};",
            ];
        }, $this->ast()->imports);
    }

    /**
     * The module a name is imported FROM — `import { useX } from './useX'` → `./useX` for
     * `useX`. The middle hop of a composable trace: find the file that declares the
     * composable. Null when the name isn't imported (locally declared, or absent).
     */
    public function importSpecifier(string $name): ?string
    {
        foreach ($this->ast()->imports as $import) {
            if (array_key_exists($name, $import->bindings) && $import->source !== null) {
                return $import->source;
            }
        }

        return null;
    }

    private function unquoteString(string $raw): string
    {
        return strlen($raw) >= 2 ? substr($raw, 1, -1) : $raw;
    }

    /**
     * The first string argument of a call to $callee — `import.meta.glob('./Pages/**\/*.vue')`
     * via `callStringArg('glob')` → `./Pages/**\/*.vue`. A generic between the callee and `(`
     * (`glob<T>(…)`) is stepped over. Null when there's no such call. Reads the Inertia page
     * glob (and the like) off the AST, not by scraping the entry file.
     */
    public function callStringArg(string $callee): ?string
    {
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isId($i, $callee)) {
                continue;
            }

            $open = $i + 1;

            while ($open < $count && ! $this->isPunct($open, '(')) {
                $open++; // step over a `<Generic>` between the callee and its argument list
            }

            if ($open >= $count) {
                continue;
            }

            $close = $this->matchingParen($open);

            for ($k = $open + 1; $k < $close; $k++) {
                if ($this->tokens[$k]['kind'] === Token::STRING) {
                    return $this->unquoteString($this->tokens[$k]['value']);
                }
            }
        }

        return null;
    }

    /**
     * The fields of an `interface`/`type` DECLARED in this script — `{ a: T; m(): R }` →
     * `['a' => 'T', 'm' => '() => R']`. Empty when the type isn't an object shape declared
     * here (an enum union, or imported/re-exported — {@see TypeResolver} follows those).
     *
     * @return array<string, string>
     */
    public function typeFields(string $name): array
    {
        return $this->ast()->typeDeclaration($name)?->fields() ?? [];
    }

    /**
     * The local `interface`/`type` declarations (rendered source) reachable from $names, resolved
     * transitively — what an extract carries into a child so a prop typed by a parent-local type
     * still compiles. Skips names that are imported or built-in.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    public function localTypes(array $names): array
    {
        return $this->ast()->localTypes($names);
    }

    /**
     * The fields a composable with an INFERRED return type exposes — read from its `return { … }`
     * object, each field typed from the composable's OWN declaration (a `ref`'s value type, a
     * `function`'s signature), ref-unwrapped as the template sees it. This is what a type checker
     * infers; it lets `const { taxes } = useTaxTypes()` resolve even when `useTaxTypes` has no
     * declared return. Empty when the function returns no object literal.
     *
     * @return array<string, string>
     */
    public function inferredReturnFields(string $function): array
    {
        $declaration = $this->ast()->functionNamed($function);

        if ($declaration?->returnObject === null) {
            return [];
        }

        // The returned locals are declared INSIDE the composable's body — read them from a script
        // scoped to that body (where a `const`/`function` is top-level and typeable).
        $body = new self($declaration->bodySource);
        $fields = [];

        foreach ($declaration->returnObject as $field => $local) {
            $type = $local !== null ? $body->declaredType($local) : null;

            if ($type !== null) {
                $fields[$field] = self::unwrapRef($type);
            }
        }

        return $fields;
    }

    /**
     * Every object-shaped type DECLARED in this script — each `interface Name {…}` and
     * `type Name = {…}` as its name, its field names, and the byte offset of its
     * declaration keyword (so a caller maps it to a source line). The dual of
     * {@see typeFields}, which resolves ONE known name; this enumerates them all, for a
     * detector that must compare every hand-written type against a set of contracts.
     *
     * @return list<array{name: string, fields: list<string>, offset: int}>
     */
    public function declarations(): array
    {
        $declarations = [];
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($this->isId($i, 'interface') && $this->isIdentifier($i + 1) && $this->isPunct($i + 2, '{')) {
                $declarations[] = $this->declaration($i, $i + 1, $i + 3);

                continue;
            }

            if ($this->isId($i, 'type') && $this->isIdentifier($i + 1) && $this->isPunct($i + 2, '=') && $this->isPunct($i + 3, '{')) {
                $declarations[] = $this->declaration($i, $i + 1, $i + 4);
            }
        }

        return $declarations;
    }

    /**
     * One declaration record — its name (the token at $nameAt), its field names (read
     * from the `{` body at $bodyAt) and the source offset of its keyword (at $keywordAt).
     *
     * @return array{name: string, fields: list<string>, offset: int}
     */
    private function declaration(int $keywordAt, int $nameAt, int $bodyAt): array
    {
        return [
            'name' => $this->tokens[$nameAt]['value'],
            'fields' => array_keys($this->readFields($bodyAt)),
            'offset' => $this->tokens[$keywordAt]['start'],
        ];
    }

    private function isIdentifier(int $i): bool
    {
        return ($this->tokens[$i]['kind'] ?? null) === Token::IDENTIFIER;
    }

    /**
     * Is there a line break in the source between byte offsets $from and $to — the
     * whitespace gap the lexer dropped between two tokens? Genuine delimiter scanning,
     * not structure: it recovers the member boundary TS marks with a newline.
     */
    private function newlineBetween(int $from, int $to): bool
    {
        return $to > $from && str_contains(substr($this->source, $from, $to - $from), "\n");
    }

    /**
     * The names declared as LOCALS in the script — `const`/`let`/`var` (simple and
     * destructured `{ a, b }` / aliased `{ a: b }`), plus `function name` declarations. These
     * are the parent `<script setup>` bindings: a same-named one SHADOWS a prop (`const
     * modelValue = useVModel(props, 'modelValue')` is the writable local, not the prop), and a
     * `function`/arrow among them is a callable the template may reference — what an extraction
     * must rewire as an emit rather than leave dangling.
     *
     * @return list<string>
     */
    public function localNames(): array
    {
        return array_values(array_unique($this->ast()->localNames()));
    }

    /**
     * The variable a `defineProps` result is bound to — `const props = defineProps<…>()` or
     * `const props = withDefaults(defineProps<…>(), …)` → `props`. Null when props aren't
     * captured in a variable (a standalone `defineProps<…>()`, or a `const { x } = …`
     * destructure — there is no object to read `props.x` off).
     */
    public function propsVariable(): ?string
    {
        return $this->assignedFrom(static function (VariableDecl $decl): bool {
            $callee = $decl->initCall?->callee;

            // `const props = defineProps(…)` or `const props = withDefaults(defineProps(…), …)`.
            return $callee === 'defineProps'
                || ($callee === 'withDefaults' && str_starts_with($decl->initCall->arguments[0] ?? '', 'defineProps'));
        });
    }

    /**
     * The name of the first `const NAME = …` whose initializer $matches — the binding a
     * `defineProps`/`defineEmits` macro is captured in.
     *
     * @param  callable(VariableDecl): bool  $matches
     */
    private function assignedFrom(callable $matches): ?string
    {
        foreach ($this->ast()->body as $node) {
            if ($node instanceof VariableDecl && $node->pattern instanceof NamePattern && $matches($node)) {
                return $node->pattern->name;
            }
        }

        return null;
    }

    /**
     * The variable a `defineEmits` result is bound to — `const emit = defineEmits<…>()` →
     * `emit`, or null when the component captures none (a standalone `defineEmits<…>()` whose
     * template uses the built-in `$emit`). A handler that CALLS this binding (`emit('save')`) is
     * itself emitting an event, not invoking a forwardable function — so an extraction must not
     * mistake it for one and mint an event literally named `emit`.
     */
    public function emitName(): ?string
    {
        return $this->assignedFrom(static fn (VariableDecl $decl): bool => $decl->initCall?->callee === 'defineEmits');
    }

    /**
     * Whether the script reads `$object.$member` anywhere — `accessesMember('props', 'user')`
     * for a `props.user` access. Lets a detector tell a forwarded-only prop from one the
     * script also consumes.
     */
    public function accessesMember(string $object, string $member): bool
    {
        $count = count($this->tokens);

        for ($i = 0; $i + 2 < $count; $i++) {
            if ($this->isId($i, $object) && $this->isPunct($i + 1, '.') && $this->isId($i + 2, $member)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The modules this script RE-EXPORTS from — every `export … from '…'` specifier
     * (`export * from './generated'`, `export { X } from './x'`). The edges to follow when a
     * type isn't declared locally — a barrel points onward to where it really lives.
     *
     * @return list<string>
     */
    public function reExports(): array
    {
        $count = count($this->tokens);
        $specifiers = [];

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isId($i, 'export')) {
                continue;
            }

            for ($j = $i + 1; $j < $count && ! $this->isPunct($j, ';'); $j++) {
                if (! $this->isId($j, 'from')) {
                    continue;
                }

                if (($this->tokens[$j + 1] ?? null) !== null && $this->tokens[$j + 1]['kind'] === Token::STRING) {
                    $specifiers[] = $this->unquoteString($this->tokens[$j + 1]['value']);
                }

                break;
            }
        }

        return array_values(array_unique($specifiers));
    }

    /**
     * The names an import binds, from the tokens in `[$from, $to)` — default, `* as ns`,
     * and the `{ a, b as c }` named set; `type` and aliasing handled.
     *
     * @return list<string>
     */
    /**
     * Where an import statement ends, starting from its terminator token at $j: the
     * source string after `from`, the string itself (side-effect), else the next `;`.
     *
     * @return array{0: int, 1: int}  [end offset, index to resume the outer scan at]
     */
    /**
     * The `defineProps<{ name: Type; … }>()` field types.
     *
     * @return array<string, string>
     */
    public function propTypes(): array
    {
        $shape = $this->definePropsCall()?->firstTypeArgument();

        return match (true) {
            $shape instanceof ObjectType => $shape->fields(),          // defineProps<{ … }>()
            $shape instanceof NamedType => $this->typeFields($shape->name), // defineProps<Props>()
            default => [],
        };
    }

    /**
     * The `defineProps<…>()` macro call — written directly, OR wrapped in
     * `withDefaults(defineProps<…>(), …)` (where it's the first argument). Missing the wrapped form
     * left every `withDefaults` component's props typed `unknown`.
     */
    private function definePropsCall(): ?CallExpr
    {
        if (($direct = $this->ast()->call('defineProps')) !== null) {
            return $direct;
        }

        $wrapped = $this->ast()->call('withDefaults')?->arguments[0] ?? null;

        return $wrapped !== null ? TsParser::module($wrapped)->call('defineProps') : null;
    }

    /**
     * The declared TS type of a top-level local, traced to its declaration — an
     * explicit annotation (`const x: T`), a reactive value type (`computed<T>` /
     * `ref<T>` etc., unwrapped as it is in the template), or a function's signature.
     * Null when it can't be read off the source (an inferred const — only vue-tsc
     * could resolve that).
     */
    public function declaredType(string $name): ?string
    {
        if (($function = $this->ast()->functionNamed($name)) !== null) {
            return $function->signature()->render();
        }

        $variable = $this->ast()->variable($name);

        if ($variable === null) {
            return null;
        }

        if ($variable->typeAnnotation !== null) {
            return $variable->typeAnnotation->render();
        }

        if ($variable->initParams !== null) { // `= (params): R =>` — an arrow function
            return new FunctionType($variable->initParams, $variable->initReturnType ?? new KeywordType('void'))->render();
        }

        return $variable->initCall !== null ? $this->reactiveType($variable->initCall) : null;
    }

    /**
     * The function a name is destructured from — `const { step, fields } = useWizardState(…)`
     * → `useWizardState` for `step`. The first hop of a composable trace: a binding pulled
     * out of a composable's return, so its type lives in that composable. Null when the name
     * isn't object-destructured from a call. An `= await call()` is seen through.
     */
    public function destructuredCall(string $name): ?string
    {
        $variable = $this->ast()->variable($name);

        return $variable?->pattern instanceof ObjectPattern ? $variable->initCall?->callee : null;
    }

    /**
     * A function's DECLARED return type name — `function useX(): WizardState` or
     * `const useX = (): WizardState =>` → `WizardState`. Null when the function isn't found
     * or has no return annotation (an inferred return — only a type checker could resolve it).
     */
    public function returnTypeName(string $function): ?string
    {
        if (($declaration = $this->ast()->functionNamed($function)) !== null) {
            return $declaration->returnType?->render();
        }

        return $this->ast()->variable($function)?->initReturnType?->render();
    }

    /**
     * The type of one field of a named `interface`/`type` in this script, as seen at a
     * binding site — a `Ref<T>` field unwraps to `T` (the value, the way the template sees
     * it). Null when the type or field isn't found. The last hop of a composable trace.
     */
    public function fieldType(string $type, string $field): ?string
    {
        $fields = $this->typeFields($type);

        return isset($fields[$field]) ? self::unwrapRef($fields[$field]) : null;
    }

    /** A `Ref<T>` / `ComputedRef<T>` (etc.) unwrapped to its value type `T`, as in the template. */
    public static function unwrapRef(string $type): string
    {
        foreach (['Ref', 'ComputedRef', 'ShallowRef', 'WritableComputedRef'] as $wrapper) {
            $prefix = $wrapper . '<';

            if (str_starts_with($type, $prefix) && str_ends_with($type, '>')) {
                return substr($type, strlen($prefix), -1);
            }
        }

        return $type;
    }

    private function isDeclarator(int $i): bool
    {
        return $this->isId($i, 'const') || $this->isId($i, 'let') || $this->isId($i, 'var');
    }

    /**
     * The SOURCE of the object literal that follows `key:` — `objectAfter('alias')` over
     * `resolve: { alias: { … } }` returns the `{ … }` text, for the expression engine to
     * parse into an AST. Null when there's no such object. (Used to read a Vite/TS config's
     * alias map structurally, never by regex.)
     */
    public function objectAfter(string $key): ?string
    {
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($this->isId($i, $key) && $this->isPunct($i + 1, ':') && $this->isPunct($i + 2, '{')) {
                $close = $this->matchingParen($i + 2);

                return substr($this->source, $this->tokens[$i + 2]['start'], $this->tokens[$close]['end'] - $this->tokens[$i + 2]['start']);
            }
        }

        return null;
    }

    /**
     * The SOURCE of a declarator's initializer — `const src = path.resolve(dir, 'x')` →
     * `path.resolve(dir, 'x')`, read up to the statement's top-level `;`. Null when the name
     * isn't declared. Lets the config reader resolve a base variable to its expression.
     */
    public function declaratorValue(string $name): ?string
    {
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isDeclarator($i) || ! $this->isId($i + 1, $name) || ! $this->isPunct($i + 2, '=')) {
                continue;
            }

            if (($this->tokens[$i + 3] ?? null) === null) {
                return null;
            }

            $from = $this->tokens[$i + 3]['start'];
            $depth = 0;
            $to = $this->tokens[$count - 1]['end'];

            for ($j = $i + 3; $j < $count; $j++) {
                $value = $this->tokens[$j]['value'];

                if (Token::opensGroup($value)) {
                    $depth++;
                } elseif (Token::closesGroup($value)) {
                    $depth--;
                } elseif ($depth === 0 && $value === ';') {
                    $to = $this->tokens[$j]['start'];

                    break;
                }
            }

            return trim(substr($this->source, $from, $to - $from));
        }

        return null;
    }

    /** Vue reactivity wrappers whose `<T>` IS the value type seen in the template. */
    /**
     * The type of a function declared at the `(` token $i — `(params) => Return`.
     */
    private function functionType(int $i): ?string
    {
        [$params, $j] = $this->readBalanced($i);
        $return = 'void';

        if ($this->isPunct($j, ':')) {
            [$return] = $this->readReturnType($j + 1);
        }

        return "({$params}) => " . ($return !== '' ? $return : 'void');
    }

    /**
     * Read a FUNCTION's return type — like {@see readType}, but it stops at the function
     * BODY (`{` for a declaration, `=>` for an arrow), so `(): Promise<void> { … }` yields
     * `Promise<void>` and never swallows the body as an object type.
     *
     * @return array{0: string, 1: int}
     */
    private function readReturnType(int $i): array
    {
        $depth = 0;
        $pieces = [];
        $count = count($this->tokens);

        for (; $i < $count; $i++) {
            $value = $this->tokens[$i]['value'];

            if ($depth === 0 && in_array($value, ['{', '=', ';', ',', '}'], true)) {
                break; // the body brace, the `=>` arrow, or a terminator — the type ended.
            }

            if (in_array($value, ['<', '(', '['], true)) {
                $depth++;
            } elseif (in_array($value, ['>', ')', ']'], true)) {
                $depth--;
            }

            $pieces[] = $value;
        }

        return [implode('', $pieces), $i];
    }

    /**
     * The inner text of a `<…>` generic opened just before $i (depth 1), to its match.
     */
    /**
     * The TS type of a reactive wrapper's value, inferred from its initializer — `ref(false)`
     * → `boolean`, `computed(() => a === b)` → `boolean`. The argument's SOURCE is sliced out
     * by the parens' known byte offsets and handed to the expression engine: a `computed`
     * getter's value is its callback's RETURN, every other wrapper's is the argument itself.
     * Null for an initializer the engine can't infer soundly (an identifier, call, object).
     */
    /**
     * The index of the bracket that closes the one opening at $open — counting only
     * round/square/curly brackets, NOT `<`/`>`. In an expression body `<`/`>` are the
     * arrow `=>` and comparison operators, not delimiters, so counting them (as the
     * type-level {@see readBalanced} must) would close the span early.
     */
    private function matchingParen(int $open): int
    {
        $depth = 0;
        $count = count($this->tokens);

        for ($i = $open; $i < $count; $i++) {
            $value = $this->tokens[$i]['value'];

            if (Token::opensGroup($value)) {
                $depth++;
            } elseif (Token::closesGroup($value) && --$depth === 0) {
                return $i;
            }
        }

        return $count - 1;
    }

    /**
     * The inner text of a balanced `(…)` opening at $i, and the index just past `)`.
     *
     * @return array{0: string, 1: int}
     */
    private function readBalanced(int $i): array
    {
        $depth = 0;
        $pieces = [];
        $count = count($this->tokens);

        for (; $i < $count; $i++) {
            $value = $this->tokens[$i]['value'];

            if (Token::opensType($value)) {
                $depth++;
            } elseif (Token::closesType($value) && --$depth === 0) {
                return [implode('', array_slice($pieces, 1)), $i + 1];
            }

            $pieces[] = $value;
        }

        return [implode('', array_slice($pieces, 1)), $count];
    }

    /**
     * Read the members of a `{ … }` opening at $i until its matching `}`. A `key: Type`
     * data field keeps its type; a METHOD member (`name(args): R`) is recorded as the
     * function type `(args) => R` — and consumed WHOLE, so a parameter sharing a field's
     * name (`step` in `goToStep(step: string)` beside `step: Ref<string>`) can never leak
     * out and overwrite the real field.
     *
     * @return array<string, string>
     */
    private function readFields(int $i): array
    {
        $types = [];
        $count = count($this->tokens);

        while ($i < $count && ! $this->isPunct($i, '}')) {
            if ($this->tokens[$i]['kind'] !== Token::IDENTIFIER) {
                $i++;

                continue;
            }

            $name = $this->tokens[$i]['value'];
            $i++;

            if ($this->isPunct($i, '?')) {
                $i++;
            }

            // `name(…): R` is a method — its binding-site type is the function `(…) => R`.
            if ($this->isPunct($i, '(')) {
                $types[$name] = $this->functionType($i) ?? 'unknown';
                $i = $this->matchingParen($i) + 1;

                while ($i < $count && ! $this->isPunct($i, ';') && ! $this->isPunct($i, '}')) {
                    $i++;
                }

                continue;
            }

            if (! $this->isPunct($i, ':')) {
                continue;
            }

            [$type, $i] = $this->readType($i + 1);
            $types[$name] = $type;
        }

        return $types;
    }

    /**
     * Read a type's tokens until a top-level `;`/`,`/`}` — respecting `<>`, `()`,
     * `[]`, `{}` nesting — and reconstruct its text.
     *
     * @return array{0: string, 1: int}  [type, next index]
     */
    private function readType(int $i): array
    {
        $depth = 0;
        $pieces = [];
        $count = count($this->tokens);
        $previousEnd = null;

        for (; $i < $count; $i++) {
            $token = $this->tokens[$i];
            $value = $token['value'];

            // The `=>` of a function type is two tokens: the `=` is NOT an initializer and the
            // `>` is NOT a generic close. Consume both so `(x: T) => R` reads whole — otherwise
            // the arrow's `=` looks like a terminator and the type truncates to `(x: T)`.
            if ($value === '=' && $this->isPunct($i + 1, '>')) {
                $pieces[] = '=';
                $pieces[] = '>';
                $previousEnd = $this->tokens[$i + 1]['end'];
                $i++;

                continue;
            }

            // A depth-0 `=` starts an initializer (`: T = value`) — the type ended before it.
            if ($depth === 0 && in_array($value, [';', ',', '}', '='], true)) {
                break;
            }

            // A depth-0 line break ends the type: interface/type members are newline-
            // terminated when written without a `;`. A break INSIDE `{…}`/`(…)`/`<…>`
            // (depth > 0) is part of a multi-line member, so it does not end the type.
            if ($depth === 0 && $previousEnd !== null && $this->newlineBetween($previousEnd, $token['start'])) {
                break;
            }

            if (Token::opensType($value)) {
                $depth++;
            } elseif (Token::closesType($value)) {
                $depth--;
            }

            $pieces[] = $value;
            $previousEnd = $token['end'];
        }

        if ($i < $count && $this->isPunct($i, ';')) {
            $i++;
        }

        return [implode('', $pieces), $i];
    }

    // ---- lexer ----------------------------------------------------------------

    /**
     * @return list<array{kind: string, value: string, start: int, end: int}>
     */
    private function lex(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        $i = 0;

        while ($i < $length) {
            $char = $source[$i];

            if (ctype_space($char)) {
                $i++;
            } elseif ($char === '/' && ($source[$i + 1] ?? '') === '/') {
                $i = (($nl = strpos($source, "\n", $i)) === false) ? $length : $nl;
            } elseif ($char === '/' && ($source[$i + 1] ?? '') === '*') {
                $i = (($end = strpos($source, '*/', $i)) === false) ? $length : $end + 2;
            } elseif ($char === '"' || $char === "'" || $char === '`') {
                $start = $i;
                $i = $this->skipString($source, $i, $char, $length);
                $tokens[] = ['kind' => Token::STRING, 'value' => substr($source, $start, $i - $start), 'start' => $start, 'end' => $i];
            } elseif (ctype_alpha($char) || $char === '_' || $char === '$') {
                $start = $i;
                while ($i < $length && (ctype_alnum($source[$i]) || $source[$i] === '_' || $source[$i] === '$')) {
                    $i++;
                }
                $tokens[] = ['kind' => Token::IDENTIFIER, 'value' => substr($source, $start, $i - $start), 'start' => $start, 'end' => $i];
            } elseif (ctype_digit($char)) {
                while ($i < $length && (ctype_alnum($source[$i]) || $source[$i] === '.')) {
                    $i++;
                }
            } else {
                $tokens[] = ['kind' => Token::PUNCTUATION, 'value' => $char, 'start' => $i, 'end' => $i + 1];
                $i++;
            }
        }

        return $tokens;
    }

    private function skipString(string $source, int $i, string $quote, int $length): int
    {
        for ($i++; $i < $length; $i++) {
            if ($source[$i] === '\\') {
                $i++;
            } elseif ($source[$i] === $quote) {
                return $i + 1;
            }
        }

        return $length;
    }

    private function isId(int $i, string $value): bool
    {
        return ($this->tokens[$i] ?? null) !== null && $this->tokens[$i]['kind'] === Token::IDENTIFIER && $this->tokens[$i]['value'] === $value;
    }

    private function isPunct(int $i, string $value): bool
    {
        return ($this->tokens[$i] ?? null) !== null && $this->tokens[$i]['kind'] === Token::PUNCTUATION && $this->tokens[$i]['value'] === $value;
    }
}
