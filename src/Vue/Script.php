<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Vue\Expr\Parser;

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

    public function __construct(private readonly string $source)
    {
        $this->tokens = $this->lex($source);
    }

    /**
     * Every import, with the names it binds and its exact source text.
     *
     * @return list<array{names: list<string>, statement: string}>
     */
    public function imports(): array
    {
        $imports = [];
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isId($i, 'import')) {
                continue;
            }

            $start = $this->tokens[$i]['start'];

            // The binding part runs to the FIRST terminator: `from` (ES import),
            // `=` (TS `import X = ns.Type`), `;`, or a string (side-effect import).
            $j = $i + 1;

            for (; $j < $count; $j++) {
                $token = $this->tokens[$j];

                if (($token['kind'] === Token::IDENTIFIER && $token['value'] === 'from')
                    || $token['kind'] === Token::STRING
                    || ($token['kind'] === Token::PUNCTUATION && ($token['value'] === '=' || $token['value'] === ';'))) {
                    break;
                }
            }

            $names = $this->bindingNames($i + 1, $j);
            [$end, $i] = $this->statementEnd($j);

            $statement = rtrim(substr($this->source, $start, $end - $start));
            $imports[] = ['names' => $names, 'statement' => str_ends_with($statement, ';') ? $statement : "{$statement};"];
        }

        return $imports;
    }

    /**
     * The module a name is imported FROM — `import { useX } from './useX'` → `./useX` for
     * `useX`. The middle hop of a composable trace: find the file that declares the
     * composable. Null when the name isn't imported (locally declared, or absent).
     */
    public function importSpecifier(string $name): ?string
    {
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isId($i, 'import')) {
                continue;
            }

            $j = $i + 1;

            for (; $j < $count; $j++) {
                $token = $this->tokens[$j];

                if (($token['kind'] === Token::IDENTIFIER && $token['value'] === 'from')
                    || $token['kind'] === Token::STRING
                    || ($token['kind'] === Token::PUNCTUATION && ($token['value'] === '=' || $token['value'] === ';'))) {
                    break;
                }
            }

            if (! in_array($name, $this->bindingNames($i + 1, $j), true) || ! $this->isId($j, 'from')) {
                continue;
            }

            for ($k = $j + 1; $k < $count; $k++) {
                if ($this->tokens[$k]['kind'] === Token::STRING) {
                    return $this->unquoteString($this->tokens[$k]['value']);
                }
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
        return $this->namedTypeFields($name);
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
        $names = [];
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            // `function name(…)` / `async function name(…)` — a declared callable.
            if ($this->isId($i, 'function') && ($this->tokens[$i + 1]['kind'] ?? null) === Token::IDENTIFIER) {
                $names[] = $this->tokens[$i + 1]['value'];

                continue;
            }

            if (! $this->isDeclarator($i) || ($this->tokens[$i + 1] ?? null) === null) {
                continue;
            }

            $next = $i + 1;

            if ($this->tokens[$next]['kind'] === Token::IDENTIFIER) {
                $names[] = $this->tokens[$next]['value'];
            } elseif ($this->isPunct($next, Token::BRACE_OPEN) || $this->isPunct($next, Token::BRACKET_OPEN)) {
                $close = $this->matchingParen($next);

                for ($k = $next + 1; $k < $close; $k++) {
                    // A binding is an id that is NOT a key (`{ a: b }` binds `b`, not `a`).
                    if ($this->tokens[$k]['kind'] === Token::IDENTIFIER && ! $this->isPunct($k + 1, ':')) {
                        $names[] = $this->tokens[$k]['value'];
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The variable a `defineProps` result is bound to — `const props = defineProps<…>()` or
     * `const props = withDefaults(defineProps<…>(), …)` → `props`. Null when props aren't
     * captured in a variable (a standalone `defineProps<…>()`, or a `const { x } = …`
     * destructure — there is no object to read `props.x` off).
     */
    public function propsVariable(): ?string
    {
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isDeclarator($i) || ($this->tokens[$i + 1]['kind'] ?? null) !== Token::IDENTIFIER || ! $this->isPunct($i + 2, '=')) {
                continue;
            }

            $rhs = $i + 3;

            if ($this->isId($rhs, 'withDefaults') && $this->isPunct($rhs + 1, '(')) {
                $rhs += 2;
            }

            if ($this->isId($rhs, 'defineProps')) {
                return $this->tokens[$i + 1]['value'];
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
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isDeclarator($i) || ($this->tokens[$i + 1]['kind'] ?? null) !== Token::IDENTIFIER || ! $this->isPunct($i + 2, '=')) {
                continue;
            }

            if ($this->isId($i + 3, 'defineEmits')) {
                return $this->tokens[$i + 1]['value'];
            }
        }

        return null;
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
    private function bindingNames(int $from, int $to): array
    {
        $names = [];

        for ($k = $from; $k < $to; $k++) {
            $token = $this->tokens[$k];

            if ($token['kind'] !== Token::IDENTIFIER || $token['value'] === 'type') {
                continue;
            }

            if ($token['value'] === 'as') {
                array_pop($names); // the alias (next id) replaces the local before it

                continue;
            }

            $names[] = $token['value'];
        }

        return array_values(array_unique($names));
    }

    /**
     * Where an import statement ends, starting from its terminator token at $j: the
     * source string after `from`, the string itself (side-effect), else the next `;`.
     *
     * @return array{0: int, 1: int}  [end offset, index to resume the outer scan at]
     */
    private function statementEnd(int $j): array
    {
        $count = count($this->tokens);

        if ($this->isId($j, 'from')) {
            for ($k = $j + 1; $k < $count; $k++) {
                if ($this->tokens[$k]['kind'] === Token::STRING) {
                    return [$this->tokens[$k]['end'], $k];
                }
            }
        } elseif (($this->tokens[$j] ?? null) !== null && $this->tokens[$j]['kind'] === Token::STRING) {
            return [$this->tokens[$j]['end'], $j];
        }

        for ($k = $j; $k < $count; $k++) {
            if ($this->isPunct($k, ';')) {
                return [$this->tokens[$k]['end'], $k];
            }
        }

        return [$this->tokens[min($j, $count - 1)]['end'] ?? 0, $count];
    }

    /**
     * The `defineProps<{ name: Type; … }>()` field types.
     *
     * @return array<string, string>
     */
    public function propTypes(): array
    {
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isId($i, 'defineProps') || ! $this->isPunct($i + 1, '<')) {
                continue;
            }

            if ($this->isPunct($i + 2, '{')) {
                return $this->readFields($i + 3); // defineProps<{ … }>()
            }

            if (($this->tokens[$i + 2] ?? null) !== null && $this->tokens[$i + 2]['kind'] === Token::IDENTIFIER) {
                return $this->namedTypeFields($this->tokens[$i + 2]['value']); // defineProps<Props>()
            }
        }

        return [];
    }

    /**
     * The fields of a named type used by `defineProps<Name>()` — its `interface Name {…}`
     * or `type Name = {…}` declaration.
     *
     * @return array<string, string>
     */
    private function namedTypeFields(string $name): array
    {
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($this->isId($i, 'interface') && $this->isId($i + 1, $name) && $this->isPunct($i + 2, '{')) {
                return $this->readFields($i + 3);
            }

            if ($this->isId($i, 'type') && $this->isId($i + 1, $name) && $this->isPunct($i + 2, '=') && $this->isPunct($i + 3, '{')) {
                return $this->readFields($i + 4);
            }
        }

        return [];
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
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($this->isId($i, 'function') && $this->isId($i + 1, $name) && $this->isPunct($i + 2, '(')) {
                return $this->functionType($i + 2);
            }

            if (! $this->isId($i + 1, $name) || ! ($this->isId($i, 'const') || $this->isId($i, 'let') || $this->isId($i, 'var'))) {
                continue;
            }

            $j = $i + 2;

            if ($this->isPunct($j, ':')) {
                [$type] = $this->readType($j + 1);

                return $type !== '' ? $type : null;
            }

            // `= computed<T>(` / `= ref<T>(` — the reactive value type (unwrapped in template).
            if ($this->isPunct($j, '=') && $this->isReactiveValue($j + 1) && $this->isPunct($j + 2, '<')) {
                $generic = $this->readGeneric($j + 3);

                if ($generic !== '') {
                    return $generic;
                }
            }

            // `= ref(false)` / `= computed(() => a === b)` — no generic, so infer the value
            // type from the initializer (the common case TS infers and vue-tsc would resolve).
            if ($this->isPunct($j, '=') && $this->isReactiveValue($j + 1) && $this->isPunct($j + 2, '(')) {
                $inferred = $this->reactiveInitType($this->tokens[$j + 1]['value'], $j + 2);

                if ($inferred !== null) {
                    return $inferred;
                }
            }

            // `= (params): R =>` / `= (params) =>` — an arrow function.
            if ($this->isPunct($j, '=') && $this->isPunct($j + 1, '(')) {
                return $this->functionType($j + 1);
            }
        }

        return null;
    }

    /**
     * The function a name is destructured from — `const { step, fields } = useWizardState(…)`
     * → `useWizardState` for `step`. The first hop of a composable trace: a binding pulled
     * out of a composable's return, so its type lives in that composable. Null when the name
     * isn't object-destructured from a call. An `= await call()` is seen through.
     */
    public function destructuredCall(string $name): ?string
    {
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isDeclarator($i) || ! $this->isPunct($i + 1, '{')) {
                continue;
            }

            $j = $i + 2;
            $keys = [];

            for (; $j < $count && ! $this->isPunct($j, '}'); $j++) {
                // A binding is an id that is NOT a key (a key is followed by `:`, its binding
                // is the id after the `:`). So `{ targets: ours }` binds `ours`, not `targets`.
                if ($this->tokens[$j]['kind'] === Token::IDENTIFIER && ! $this->isPunct($j + 1, ':')) {
                    $keys[] = $this->tokens[$j]['value'];
                }
            }

            $j++; // past `}`

            if (! $this->isPunct($j, '=')) {
                continue;
            }

            $j += $this->isId($j + 1, 'await') ? 2 : 1;

            if (($this->tokens[$j] ?? null) !== null && $this->tokens[$j]['kind'] === Token::IDENTIFIER
                && $this->isPunct($j + 1, '(') && in_array($name, $keys, true)) {
                return $this->tokens[$j]['value'];
            }
        }

        return null;
    }

    /**
     * A function's DECLARED return type name — `function useX(): WizardState` or
     * `const useX = (): WizardState =>` → `WizardState`. Null when the function isn't found
     * or has no return annotation (an inferred return — only a type checker could resolve it).
     */
    public function returnTypeName(string $function): ?string
    {
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($this->isId($i, 'function') && $this->isId($i + 1, $function) && $this->isPunct($i + 2, '(')) {
                return $this->returnAfterParams($i + 2);
            }

            if ($this->isDeclarator($i) && $this->isId($i + 1, $function) && $this->isPunct($i + 2, '=') && $this->isPunct($i + 3, '(')) {
                return $this->returnAfterParams($i + 3);
            }
        }

        return null;
    }

    /**
     * The return type annotated after a parameter list opening at $openParen — the `: R`
     * that follows the matching `)`. Null when there is none.
     */
    private function returnAfterParams(int $openParen): ?string
    {
        $after = $this->matchingParen($openParen) + 1;

        if (! $this->isPunct($after, ':')) {
            return null;
        }

        [$type] = $this->readReturnType($after + 1);

        return $type !== '' ? $type : null;
    }

    /**
     * The type of one field of a named `interface`/`type` in this script, as seen at a
     * binding site — a `Ref<T>` field unwraps to `T` (the value, the way the template sees
     * it). Null when the type or field isn't found. The last hop of a composable trace.
     */
    public function fieldType(string $type, string $field): ?string
    {
        $fields = $this->namedTypeFields($type);

        return isset($fields[$field]) ? $this->unwrapRef($fields[$field]) : null;
    }

    /** A `Ref<T>` / `ComputedRef<T>` (etc.) unwrapped to its value type `T`, as in the template. */
    private function unwrapRef(string $type): string
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
    private function isReactiveValue(int $i): bool
    {
        return ($this->tokens[$i] ?? null) !== null
            && $this->tokens[$i]['kind'] === Token::IDENTIFIER
            && in_array($this->tokens[$i]['value'], ['ref', 'computed', 'shallowRef', 'toRef', 'customRef', 'reactive'], true);
    }

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
    private function readGeneric(int $i): string
    {
        $depth = 1;
        $pieces = [];
        $count = count($this->tokens);

        for (; $i < $count && $depth > 0; $i++) {
            $value = $this->tokens[$i]['value'];

            if (Token::opensType($value)) {
                $depth++;
            } elseif (Token::closesType($value) && --$depth === 0) {
                break;
            }

            $pieces[] = $value;
        }

        return implode('', $pieces);
    }

    /**
     * The TS type of a reactive wrapper's value, inferred from its initializer — `ref(false)`
     * → `boolean`, `computed(() => a === b)` → `boolean`. The argument's SOURCE is sliced out
     * by the parens' known byte offsets and handed to the expression engine: a `computed`
     * getter's value is its callback's RETURN, every other wrapper's is the argument itself.
     * Null for an initializer the engine can't infer soundly (an identifier, call, object).
     */
    private function reactiveInitType(string $wrapper, int $openParen): ?string
    {
        $close = $this->matchingParen($openParen);

        $from = $this->tokens[$openParen]['end'];
        $to = $this->tokens[$close]['start'] ?? $from;
        $argument = trim(substr($this->source, $from, $to - $from));

        if ($argument === '') {
            return null;
        }

        $expression = Parser::parse($argument);

        return $wrapper === 'computed' ? $expression->returnType() : $expression->inferType();
    }

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

    private function isKeyword(int $i, string $word): bool
    {
        return $this->isId($i, $word);
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
