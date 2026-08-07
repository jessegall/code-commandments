<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Expr;

use JesseGall\CodeCommandments\Vue\Token;

/**
 * Parses a Vue binding expression (the JS in `:x="…"` / `v-if="…"` / `{{ … }}`)
 * into an {@see Expr} tree. A hand-written lexer + Pratt parser — NO regex: member
 * chains, calls, equality and the rest are recovered as structure, the way the
 * backend recovers them from php-parser. Unfamiliar syntax degrades to an
 * {@see ExprKind::Unknown} node rather than throwing, so a detector always gets a tree.
 */
final class Parser
{
    /**
     * Binary operators, loosest-binding first; ternary `?:` sits below all of them.
     */
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
        '.', '(', ')', '[', ']', '{', '}', ',', '?', ':', '!', '<', '>', '+', '-', '*', '/', '%', '=',
    ];

    /**
     * @var list<array{type: string, value: string}>
     */
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

        return new Expr(ExprKind::For, ['aliases' => $aliases, 'keyword' => $keyword, 'iterable' => $iterable]);
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
        $left = $this->ternary();

        // Assignment is the loosest binding and right-associative — `x = expr` (a `@click`
        // handler writing a value). `==`/`===`/`<=`/`>=` are their own tokens, so a lone `=`
        // here is only ever assignment.
        if ($this->isPunct('=')) {
            $this->next();

            return new Expr(ExprKind::Assign, ['target' => $left, 'value' => $this->expression()]);
        }

        return $left;
    }

    private function ternary(): Expr
    {
        $test = $this->binary(0);

        if ($this->isPunct('?')) {
            $this->next();
            $then = $this->expression();
            $this->expect(':');
            $else = $this->expression();

            return new Expr(ExprKind::Conditional, ['test' => $test, 'then' => $then, 'else' => $else]);
        }

        return $test;
    }

    private function binary(int $minPrecedence): Expr
    {
        $left = $this->unary();

        // A TS `as` cast is compile-time-only — skip `as <Type>` and keep the runtime value expression, so
        // `($event.target as HTMLInputElement).value` parses (and reconstructs) as `$event.target.value`
        // rather than silently truncating to `$event.target`.
        while ($this->peek()['type'] === 'name' && $this->peek()['value'] === 'as') {
            $this->next();
            $this->skipTypeAnnotation();
        }

        while (true) {
            $token = $this->peek();
            $operator = $token['value'];

            if ($token['type'] !== 'punct' || ! isset(self::PRECEDENCE[$operator]) || self::PRECEDENCE[$operator] < $minPrecedence) {
                break;
            }

            $this->next();
            $right = $this->binary(self::PRECEDENCE[$operator] + 1);
            $left = new Expr(ExprKind::Binary, ['op' => $operator, 'left' => $left, 'right' => $right]);
        }

        return $left;
    }

    private function unary(): Expr
    {
        $token = $this->peek();

        if ($token['type'] === 'punct' && in_array($token['value'], ['!', '-', '+'], true)) {
            $this->next();

            return new Expr(ExprKind::Unary, ['op' => $token['value'], 'argument' => $this->unary()]);
        }

        if ($token['type'] === 'name' && $token['value'] === 'typeof') {
            $this->next();

            return new Expr(ExprKind::Unary, ['op' => 'typeof', 'argument' => $this->unary()]);
        }

        return $this->postfix();
    }

    /**
     * Consume (and discard) a TS type after `as` — a name plus its type suffixes (`.Name`, balanced
     * `<…>`/`[…]`/`(…)`, `|`/`&` unions) — stopping at a token that ends the type in this position: a
     * closing bracket at depth 0, or any non-connector punct (a comma, `?`/`:`, a value operator). The
     * cast is erased; only the runtime value expression it wraps is kept.
     */
    private function skipTypeAnnotation(): void
    {
        $depth = 0;

        while (true) {
            $token = $this->peek();

            if ($token['type'] === 'eof') {
                return;
            }

            if ($token['type'] === 'punct') {
                $value = $token['value'];

                if (in_array($value, ['<', '[', '('], true)) {
                    $depth++;
                    $this->next();

                    continue;
                }

                if (in_array($value, ['>', ']', ')'], true)) {
                    if ($depth === 0) {
                        return; // a closing bracket belonging to the enclosing expression
                    }

                    $depth--;
                    $this->next();

                    continue;
                }

                if ($depth === 0 && ! in_array($value, ['.', '|', '&'], true)) {
                    return; // a top-level token that isn't a type connector ends the type
                }
            }

            $this->next();
        }
    }

    private function postfix(): Expr
    {
        $node = $this->primary();

        while (($extended = $this->extended($node)) !== null) {
            $node = $extended;
        }

        return $node;
    }

    /**
     * $node with the NEXT postfix operator applied — `.x`, `?.x`, `[i]` or `(args)` — or null when
     * the next token is none of those, which is where the chain ends. One operator per arm, so a
     * new one is a line rather than another rung.
     */
    private function extended(Expr $node): ?Expr
    {
        return match (true) {
            $this->isPunct('.') => $this->member($node, optional: false),
            $this->isPunct('?.') => $this->optionalMember($node),
            $this->isPunct('[') => $this->index($node),
            $this->isPunct('(') => new Expr(ExprKind::Call, ['callee' => $node, 'arguments' => $this->arguments()]),
            default => null,
        };
    }

    /**
     * `$node.name`, or `$node?.name` when $optional.
     */
    private function member(Expr $node, bool $optional): Expr
    {
        $this->next();

        return new Expr(ExprKind::Member, ['object' => $node, 'property' => $this->name(), 'optional' => $optional]);
    }

    /**
     * What follows a `?.` — an optional member, or the `?.[`/`?.(` forms {@see tail} builds.
     */
    private function optionalMember(Expr $node): Expr
    {
        $this->next();

        return $this->isPunct('[') || $this->isPunct('(')
            ? $this->tail($node, true)
            : new Expr(ExprKind::Member, ['object' => $node, 'property' => $this->name(), 'optional' => true]);
    }

    /**
     * `$node[index]`.
     */
    private function index(Expr $node): Expr
    {
        $this->next();
        $index = $this->expression();
        $this->expect(']');

        return new Expr(ExprKind::Index, ['object' => $node, 'index' => $index]);
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

            return new Expr(ExprKind::Index, ['object' => $node, 'index' => $index, 'optional' => $optional]);
        }

        return new Expr(ExprKind::Call, ['callee' => $node, 'arguments' => $this->arguments(), 'optional' => $optional]);
    }

    private function primary(): Expr
    {
        $token = $this->peek();

        if ($token['type'] === 'name') {
            $this->next();

            if (in_array($token['value'], ['true', 'false', 'null', 'undefined'], true)) {
                return new Expr(ExprKind::Literal, ['value' => $token['value'], 'raw' => $token['value']]);
            }

            if ($this->isPunct('=>')) {
                $this->next();
                $param = new Expr(ExprKind::Identifier, ['name' => $token['value']]);

                return new Expr(ExprKind::Arrow, ['params' => [$param], 'body' => $this->expression()]);
            }

            return new Expr(ExprKind::Identifier, ['name' => $token['value']]);
        }

        if ($token['type'] === 'num') {
            $this->next();

            return new Expr(ExprKind::Literal, ['value' => $token['value'], 'raw' => $token['value']]);
        }

        if ($token['type'] === 'str') {
            $this->next();

            return new Expr(ExprKind::Literal, ['value' => $this->unquote($token['value']), 'raw' => $token['value']]);
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

        return new Expr(ExprKind::Unknown);
    }

    private function group(): Expr
    {
        $this->expect('(');

        // A parenthesised group is an arrow's PARAMETER LIST when its matching `)` is followed by
        // `=>`; otherwise it's a grouped expression. Deciding up front (not retroactively) lets us
        // read TS-typed params — `(v: string | number) => …` — which the expression grammar can't,
        // and whose annotation would otherwise leak the param name as a free read.
        if ($this->closingParenLeadsToArrow()) {
            $params = $this->arrowParameters();
            $this->expect(')');
            $this->skipArrowMarker();

            return new Expr(ExprKind::Arrow, ['params' => $params, 'body' => $this->expression()]);
        }

        if ($this->isPunct(')')) {
            $this->next();

            return new Expr(ExprKind::Unknown);
        }

        $inner = $this->expression();
        $this->expect(')');

        return $inner;
    }

    /**
     * Does the `)` that closes the just-opened `(` come immediately before a `=>`? A pure token
     * lookahead (depth-balanced over every bracket kind) that classifies the group as an arrow
     * parameter list vs a grouped expression, without committing the parse.
     */
    private function closingParenLeadsToArrow(): bool
    {
        $depth = 0;

        for ($i = $this->pos, $n = count($this->tokens); $i < $n; $i++) {
            $token = $this->tokens[$i];

            if ($token['type'] !== 'punct') {
                continue;
            }

            if (in_array($token['value'], ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($token['value'], [')', ']', '}'], true)) {
                if ($depth === 0) {
                    $next = $this->tokens[$i + 1] ?? null;

                    return $next !== null && $next['type'] === 'punct' && $next['value'] === '=>';
                }

                $depth--;
            }
        }

        return false;
    }

    /**
     * An arrow's parameter names — each identifier (or the identifiers a destructuring pattern
     * binds), with its `: Type` annotation and `= default` skipped. Returned as IDENTIFIER nodes so
     * {@see Expr::roots} can subtract them from the body's free reads.
     *
     * @return list<Expr>
     */
    private function arrowParameters(): array
    {
        $params = [];

        while ($this->insideGroupClosedBy(')')) {
            if ($this->isPunct('{') || $this->isPunct('[')) {
                foreach ($this->patternNames() as $name) {
                    $params[] = new Expr(ExprKind::Identifier, ['name' => $name]);
                }
            } elseif ($this->peek()['type'] === 'name') {
                $params[] = new Expr(ExprKind::Identifier, ['name' => $this->peek()['value']]);
                $this->next();
            }

            $this->skipToParamBoundary(); // a `: Type` annotation and/or `= default`

            if ($this->isPunct(',')) {
                $this->next();
            }
        }

        return $params;
    }

    /**
     * The identifier names a destructuring pattern binds, consuming it to its matching close — a
     * best-effort scan (aliases and defaults included) so every bound local is subtracted.
     *
     * @return list<string>
     */
    private function patternNames(): array
    {
        $names = [];
        $depth = 0;

        do {
            $change = $this->depthChange();
            $depth += $change;

            if ($change === 0 && $this->peek()['type'] === 'name') {
                $names[] = $this->peek()['value'];
            }

            $this->next();
        } while ($depth > 0 && ! $this->isEof());

        return $names;
    }

    /**
     * Skip a param's trailing `: Type` / `= default` up to the next top-level `,` or `)` — the
     * boundary between this parameter and the next (bracket-balanced, so a nested `(…)`/`<…>` in
     * the type doesn't end it early).
     */
    private function skipToParamBoundary(): void
    {
        $depth = 0;

        while (! $this->isEof()) {
            if ($depth === 0 && ($this->isPunct(',') || $this->isPunct(')'))) {
                return;
            }

            $depth += $this->depthChange();
            $this->next();
        }
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

        return new Expr(ExprKind::Array, ['elements' => $elements]);
    }

    private function objectLiteral(): Expr
    {
        $this->expect('{');
        $values = [];
        $keys = [];

        while (! $this->isPunct('}') && ! $this->isEof()) {
            $token = $this->peek();
            $key = in_array($token['type'], ['name', 'str', 'num'], true)
                ? ($token['type'] === 'str' ? $this->unquote($token['value']) : (string) $token['value'])
                : null;
            $this->next(); // consume the key (an identifier, string, number, or computed token)

            if ($this->isPunct(':')) {
                $this->next();
                $keys[] = $key;            // aligned with $values — only pushed for real `key: value` pairs
                $values[] = $this->expression();
            }

            if (! $this->isPunct(',')) {
                break;
            }

            $this->next();
        }

        $this->expect('}');

        return new Expr(ExprKind::Object, ['values' => $values, 'keys' => $keys]);
    }

    /**
     * @return list<Expr>
     */
    private function arguments(): array
    {
        $this->expect('(');
        $arguments = [];

        while ($this->insideGroupClosedBy(')')) {
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

    /**
     * What the token under the cursor does to a bracket DEPTH — +1 for an opener, -1 for a closer,
     * 0 for anything else. Two scans that must stay inside a group were each spelling the same
     * six-bracket ladder; this says it once, from {@see Token}'s own idea of a group.
     */
    private function depthChange(): int
    {
        $token = $this->peek();

        if ($token['type'] !== 'punct') {
            return 0;
        }

        return match (true) {
            Token::opensGroup($token['value']) => 1,
            Token::closesGroup($token['value']) => -1,
            default => 0,
        };
    }

    private function isPunct(string $value): bool
    {
        $token = $this->peek();

        return $token['type'] === 'punct' && $token['value'] === $value;
    }

    /**
     * Is there more to read inside a group that ends with $closer? Stop AT the closer, and stop at
     * EOF too, so a group whose closer never arrives cannot spin.
     */
    private function insideGroupClosedBy(string $closer): bool
    {
        return ! $this->isPunct($closer) && ! $this->isEof();
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
