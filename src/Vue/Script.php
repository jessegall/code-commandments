<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

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
            if ($this->isId($i, 'defineProps') && $this->isPunct($i + 1, '<') && $this->isPunct($i + 2, '{')) {
                return $this->readFields($i + 3);
            }
        }

        return [];
    }

    /**
     * Read `key: Type;` fields from the opening `{` at $i until its matching `}`.
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

            if ($depth === 0 && in_array($value, [';', ',', '}'], true)) {
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
