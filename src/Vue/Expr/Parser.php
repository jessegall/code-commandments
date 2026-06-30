<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Expr;

/**
 * Parses a Vue binding expression (the JS in `:x="…"` / `v-if="…"` / `{{ … }}`)
 * into an {@see Expr} tree. A hand-written lexer + Pratt parser — NO regex: member
 * chains, calls, equality and the rest are recovered as structure, the way the
 * backend recovers them from php-parser. Unfamiliar syntax degrades to an
 * {@see Expr::UNKNOWN} node rather than throwing, so a detector always gets a tree.
 */
final class Parser
{
    /** Binary operators, loosest-binding first; ternary `?:` sits below all of them. */
    private const array PRECEDENCE = [
        '??' => 1,
        '||' => 2,
        '&&' => 3,
        '===' => 4, '!==' => 4, '==' => 4, '!=' => 4,
        '<' => 5, '>' => 5, '<=' => 5, '>=' => 5,
        '+' => 6, '-' => 6,
        '*' => 7, '/' => 7, '%' => 7,
    ];

    private const array PUNCTUATION = [
        '?.', '===', '!==', '==', '!=', '<=', '>=', '&&', '||', '??', '=>',
        '.', '(', ')', '[', ']', '{', '}', ',', '?', ':', '!', '<', '>', '+', '-', '*', '/', '%',
    ];

    /** @var list<array{type: string, value: string}> */
    private array $tokens;

    private int $pos = 0;

    private function __construct(string $source)
    {
        $this->tokens = $this->lex($source);
    }

    public static function parse(string $source): Expr
    {
        $parser = new self($source);

        return $parser->expression();
    }

    /**
     * Parse a `v-for` binding into its own node — `(item, index) in group.charts` →
     * FOR{aliases: ['item','index'], keyword: 'in', iterable: <member group.charts>}. The
     * directive's grammar, read off the TOKEN stream (the keyword is a token, the alias list
     * is the names before it, the iterable is a real expression) — the engine's answer to
     * "what does this loop bind and range over", replacing an explode on `in`/`,`.
     */
    public static function parseFor(string $source): Expr
    {
        $parser = new self($source);
        $aliases = $parser->forAliases();
        $keyword = $parser->forKeyword();
        $iterable = $parser->expression();

        return new Expr(Expr::FOR, ['aliases' => $aliases, 'keyword' => $keyword, 'iterable' => $iterable]);
    }

    /**
     * The loop variables — the bare identifiers on the LHS, before the `in`/`of` keyword.
     * Names inside a destructuring `{…}`/`[…]` are bindings, not usable loop-var names, so
     * they're skipped; the grouping `(item, index)` parens are not destructuring, so their
     * names ARE collected.
     *
     * @return list<string>
     */
    private function forAliases(): array
    {
        $aliases = [];
        $bracket = 0;     // any nesting — the keyword sits at depth 0
        $destructure = 0; // only {} / [] — names within are patterns, not loop vars

        while (! $this->isEof()) {
            $token = $this->peek();

            if ($bracket === 0 && $token['type'] === 'name' && ($token['value'] === 'in' || $token['value'] === 'of')) {
                break; // the keyword — LHS is done
            }

            if ($token['type'] === 'punct') {
                if ($token['value'] === '(' || $token['value'] === '{' || $token['value'] === '[') {
                    $bracket++;
                    if ($token['value'] !== '(') {
                        $destructure++;
                    }
                } elseif ($token['value'] === ')' || $token['value'] === '}' || $token['value'] === ']') {
                    $bracket--;
                    if ($token['value'] !== ')') {
                        $destructure--;
                    }
                }
            } elseif ($token['type'] === 'name' && $destructure === 0) {
                $aliases[] = $token['value'];
            }

            $this->next();
        }

        return $aliases;
    }

    private function forKeyword(): string
    {
        $token = $this->peek();

        if ($token['type'] === 'name' && ($token['value'] === 'in' || $token['value'] === 'of')) {
            $this->next();

            return $token['value'];
        }

        return 'in';
    }

    // ---- parser ---------------------------------------------------------------

    private function expression(): Expr
    {
        $test = $this->binary(0);

        if ($this->isPunct('?')) {
            $this->next();
            $then = $this->expression();
            $this->expect(':');
            $else = $this->expression();

            return new Expr(Expr::CONDITIONAL, ['test' => $test, 'then' => $then, 'else' => $else]);
        }

        return $test;
    }

    private function binary(int $minPrecedence): Expr
    {
        $left = $this->unary();

        while (true) {
            $token = $this->peek();
            $operator = $token['value'];

            if ($token['type'] !== 'punct' || ! isset(self::PRECEDENCE[$operator]) || self::PRECEDENCE[$operator] < $minPrecedence) {
                break;
            }

            $this->next();
            $right = $this->binary(self::PRECEDENCE[$operator] + 1);
            $left = new Expr(Expr::BINARY, ['op' => $operator, 'left' => $left, 'right' => $right]);
        }

        return $left;
    }

    private function unary(): Expr
    {
        $token = $this->peek();

        if ($token['type'] === 'punct' && in_array($token['value'], ['!', '-', '+'], true)) {
            $this->next();

            return new Expr(Expr::UNARY, ['op' => $token['value'], 'argument' => $this->unary()]);
        }

        if ($token['type'] === 'name' && $token['value'] === 'typeof') {
            $this->next();

            return new Expr(Expr::UNARY, ['op' => 'typeof', 'argument' => $this->unary()]);
        }

        return $this->postfix();
    }

    private function postfix(): Expr
    {
        $node = $this->primary();

        while (true) {
            if ($this->isPunct('.')) {
                $this->next();
                $node = new Expr(Expr::MEMBER, ['object' => $node, 'property' => $this->name(), 'optional' => false]);
            } elseif ($this->isPunct('?.')) {
                $this->next();
                $node = $this->isPunct('[') || $this->isPunct('(')
                    ? $this->tail($node, true)
                    : new Expr(Expr::MEMBER, ['object' => $node, 'property' => $this->name(), 'optional' => true]);
            } elseif ($this->isPunct('[')) {
                $this->next();
                $index = $this->expression();
                $this->expect(']');
                $node = new Expr(Expr::INDEX, ['object' => $node, 'index' => $index]);
            } elseif ($this->isPunct('(')) {
                $node = new Expr(Expr::CALL, ['callee' => $node, 'arguments' => $this->arguments()]);
            } else {
                break;
            }
        }

        return $node;
    }

    /**
     * An optional-chained `?.[` index or `?.(` call.
     */
    private function tail(Expr $node, bool $optional): Expr
    {
        if ($this->isPunct('[')) {
            $this->next();
            $index = $this->expression();
            $this->expect(']');

            return new Expr(Expr::INDEX, ['object' => $node, 'index' => $index, 'optional' => $optional]);
        }

        return new Expr(Expr::CALL, ['callee' => $node, 'arguments' => $this->arguments(), 'optional' => $optional]);
    }

    private function primary(): Expr
    {
        $token = $this->peek();

        if ($token['type'] === 'name') {
            $this->next();

            if (in_array($token['value'], ['true', 'false', 'null', 'undefined'], true)) {
                return new Expr(Expr::LITERAL, ['value' => $token['value'], 'raw' => $token['value']]);
            }

            if ($this->isPunct('=>')) {
                $this->next();

                return new Expr(Expr::ARROW, ['body' => $this->expression()]);
            }

            return new Expr(Expr::IDENTIFIER, ['name' => $token['value']]);
        }

        if ($token['type'] === 'num') {
            $this->next();

            return new Expr(Expr::LITERAL, ['value' => $token['value'], 'raw' => $token['value']]);
        }

        if ($token['type'] === 'str') {
            $this->next();

            return new Expr(Expr::LITERAL, ['value' => $this->unquote($token['value']), 'raw' => $token['value']]);
        }

        if ($this->isPunct('(')) {
            return $this->group();
        }

        if ($this->isPunct('[')) {
            return $this->arrayLiteral();
        }

        if ($this->isPunct('{')) {
            return $this->objectLiteral();
        }

        // Unknown token — consume one so we always make progress.
        if ($token['type'] !== 'eof') {
            $this->next();
        }

        return new Expr(Expr::UNKNOWN);
    }

    private function group(): Expr
    {
        $this->expect('(');

        // An empty `()` is only ever an arrow's parameter list.
        if ($this->isPunct(')')) {
            $this->next();
            $this->skipArrowMarker();

            return new Expr(Expr::ARROW, ['body' => $this->expression()]);
        }

        $inner = $this->expression();

        // Multi-parameter arrow: `(a, b) => …` — keep parsing params, ignore them.
        while ($this->isPunct(',')) {
            $this->next();
            $this->expression();
        }

        $this->expect(')');

        if ($this->isPunct('=>')) {
            $this->next();

            return new Expr(Expr::ARROW, ['body' => $this->expression()]);
        }

        return $inner;
    }

    private function arrayLiteral(): Expr
    {
        $this->expect('[');
        $elements = [];

        while (! $this->isPunct(']') && ! $this->isEof()) {
            $elements[] = $this->expression();

            if (! $this->isPunct(',')) {
                break;
            }

            $this->next();
        }

        $this->expect(']');

        return new Expr(Expr::ARRAY, ['elements' => $elements]);
    }

    private function objectLiteral(): Expr
    {
        $this->expect('{');
        $values = [];

        while (! $this->isPunct('}') && ! $this->isEof()) {
            // key
            $this->next();

            if ($this->isPunct(':')) {
                $this->next();
                $values[] = $this->expression();
            }

            if (! $this->isPunct(',')) {
                break;
            }

            $this->next();
        }

        $this->expect('}');

        return new Expr(Expr::OBJECT, ['values' => $values]);
    }

    /**
     * @return list<Expr>
     */
    private function arguments(): array
    {
        $this->expect('(');
        $arguments = [];

        while (! $this->isPunct(')') && ! $this->isEof()) {
            $arguments[] = $this->expression();

            if (! $this->isPunct(',')) {
                break;
            }

            $this->next();
        }

        $this->expect(')');

        return $arguments;
    }

    private function name(): string
    {
        $token = $this->peek();

        if ($token['type'] === 'name') {
            $this->next();

            return $token['value'];
        }

        return '';
    }

    private function skipArrowMarker(): void
    {
        if ($this->isPunct('=>')) {
            $this->next();
        }
    }

    // ---- token cursor ---------------------------------------------------------

    /**
     * @return array{type: string, value: string}
     */
    private function peek(): array
    {
        return $this->tokens[$this->pos] ?? ['type' => 'eof', 'value' => ''];
    }

    private function next(): void
    {
        $this->pos++;
    }

    private function isPunct(string $value): bool
    {
        $token = $this->peek();

        return $token['type'] === 'punct' && $token['value'] === $value;
    }

    private function isEof(): bool
    {
        return $this->peek()['type'] === 'eof';
    }

    private function expect(string $punct): void
    {
        if ($this->isPunct($punct)) {
            $this->next();
        }
    }

    private function unquote(string $raw): string
    {
        if (strlen($raw) >= 2) {
            return substr($raw, 1, -1);
        }

        return $raw;
    }

    // ---- lexer (scanner, no regex) --------------------------------------------

    /**
     * @return list<array{type: string, value: string}>
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

                continue;
            }

            if ($this->isNameStart($char)) {
                $start = $i;
                while ($i < $length && $this->isNamePart($source[$i])) {
                    $i++;
                }
                $tokens[] = ['type' => 'name', 'value' => substr($source, $start, $i - $start)];

                continue;
            }

            if (ctype_digit($char)) {
                $start = $i;
                while ($i < $length && (ctype_digit($source[$i]) || $source[$i] === '.')) {
                    $i++;
                }
                $tokens[] = ['type' => 'num', 'value' => substr($source, $start, $i - $start)];

                continue;
            }

            if ($char === '"' || $char === "'" || $char === '`') {
                $tokens[] = ['type' => 'str', 'value' => $this->readString($source, $i, $length)];

                continue;
            }

            $punct = $this->readPunct($source, $i, $length);

            if ($punct !== null) {
                $tokens[] = ['type' => 'punct', 'value' => $punct];

                continue;
            }

            $i++; // unknown byte — skip
        }

        return $tokens;
    }

    private function readString(string $source, int &$i, int $length): string
    {
        $quote = $source[$i];
        $start = $i;
        $i++;

        while ($i < $length) {
            if ($source[$i] === '\\') {
                $i += 2;

                continue;
            }

            if ($source[$i] === $quote) {
                $i++;

                break;
            }

            $i++;
        }

        return substr($source, $start, $i - $start);
    }

    private function readPunct(string $source, int &$i, int $length): ?string
    {
        foreach (self::PUNCTUATION as $punct) {
            $len = strlen($punct);

            if (substr($source, $i, $len) === $punct) {
                $i += $len;

                return $punct;
            }
        }

        return null;
    }

    private function isNameStart(string $char): bool
    {
        return ctype_alpha($char) || $char === '_' || $char === '$';
    }

    private function isNamePart(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '$';
    }
}
