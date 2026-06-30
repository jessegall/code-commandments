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

                if (($token['kind'] === 'id' && $token['value'] === 'from')
                    || $token['kind'] === 'string'
                    || ($token['kind'] === 'punct' && ($token['value'] === '=' || $token['value'] === ';'))) {
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

                if (($token['kind'] === 'id' && $token['value'] === 'from')
                    || $token['kind'] === 'string'
                    || ($token['kind'] === 'punct' && ($token['value'] === '=' || $token['value'] === ';'))) {
                    break;
                }
            }

            if (! in_array($name, $this->bindingNames($i + 1, $j), true) || ! $this->isId($j, 'from')) {
                continue;
            }

            for ($k = $j + 1; $k < $count; $k++) {
                if ($this->tokens[$k]['kind'] === 'string') {
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

            if ($token['kind'] !== 'id' || $token['value'] === 'type') {
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
                if ($this->tokens[$k]['kind'] === 'string') {
                    return [$this->tokens[$k]['end'], $k];
                }
            }
        } elseif (($this->tokens[$j] ?? null) !== null && $this->tokens[$j]['kind'] === 'string') {
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

            if (($this->tokens[$i + 2] ?? null) !== null && $this->tokens[$i + 2]['kind'] === 'id') {
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
                if ($this->tokens[$j]['kind'] === 'id' && ! $this->isPunct($j + 1, ':')) {
                    $keys[] = $this->tokens[$j]['value'];
                }
            }

            $j++; // past `}`

            if (! $this->isPunct($j, '=')) {
                continue;
            }

            $j += $this->isId($j + 1, 'await') ? 2 : 1;

            if (($this->tokens[$j] ?? null) !== null && $this->tokens[$j]['kind'] === 'id'
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

    /** Vue reactivity wrappers whose `<T>` IS the value type seen in the template. */
    private function isReactiveValue(int $i): bool
    {
        return ($this->tokens[$i] ?? null) !== null
            && $this->tokens[$i]['kind'] === 'id'
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

            if (in_array($value, ['<', '(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($value, ['>', ')', ']', '}'], true) && --$depth === 0) {
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

            if (in_array($value, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($value, [')', ']', '}'], true) && --$depth === 0) {
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

            if (in_array($value, ['(', '[', '{', '<'], true)) {
                $depth++;
            } elseif (in_array($value, [')', ']', '}', '>'], true) && --$depth === 0) {
                return [implode('', array_slice($pieces, 1)), $i + 1];
            }

            $pieces[] = $value;
        }

        return [implode('', array_slice($pieces, 1)), $count];
    }

    /**
     * Read `key: Type;` fields from the opening `{` at $i until its matching `}`. A METHOD
     * member (`name(args): R`) is skipped whole — its parameters are NOT fields, so a param
     * sharing a field's name (`step` in `goToStep(step: string)` beside `step: Ref<string>`)
     * can never overwrite the real field.
     *
     * @return array<string, string>
     */
    private function readFields(int $i): array
    {
        $types = [];
        $count = count($this->tokens);

        while ($i < $count && ! $this->isPunct($i, '}')) {
            if ($this->tokens[$i]['kind'] !== 'id') {
                $i++;

                continue;
            }

            $name = $this->tokens[$i]['value'];
            $i++;

            if ($this->isPunct($i, '?')) {
                $i++;
            }

            // `name(…)` is a method — skip its signature (params + return) to the terminator.
            if ($this->isPunct($i, '(')) {
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

        for (; $i < $count; $i++) {
            $value = $this->tokens[$i]['value'];

            // A depth-0 `=` starts an initializer (`: T = value`) — the type ended before it.
            if ($depth === 0 && in_array($value, [';', ',', '}', '='], true)) {
                break;
            }

            if (in_array($value, ['<', '(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($value, ['>', ')', ']', '}'], true)) {
                $depth--;
            }

            $pieces[] = $value;
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
                $tokens[] = ['kind' => 'string', 'value' => substr($source, $start, $i - $start), 'start' => $start, 'end' => $i];
            } elseif (ctype_alpha($char) || $char === '_' || $char === '$') {
                $start = $i;
                while ($i < $length && (ctype_alnum($source[$i]) || $source[$i] === '_' || $source[$i] === '$')) {
                    $i++;
                }
                $tokens[] = ['kind' => 'id', 'value' => substr($source, $start, $i - $start), 'start' => $start, 'end' => $i];
            } elseif (ctype_digit($char)) {
                while ($i < $length && (ctype_alnum($source[$i]) || $source[$i] === '.')) {
                    $i++;
                }
            } else {
                $tokens[] = ['kind' => 'punct', 'value' => $char, 'start' => $i, 'end' => $i + 1];
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
        return ($this->tokens[$i] ?? null) !== null && $this->tokens[$i]['kind'] === 'id' && $this->tokens[$i]['value'] === $value;
    }

    private function isPunct(int $i, string $value): bool
    {
        return ($this->tokens[$i] ?? null) !== null && $this->tokens[$i]['kind'] === 'punct' && $this->tokens[$i]['value'] === $value;
    }
}
