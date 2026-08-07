<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Expr;

use JesseGall\CodeCommandments\Vue\Lexeme;
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

    /**
     * @var list<Lexeme>
     */
    private readonly array $tokens;

    private int $pos = 0;

    private function __construct(string $source)
    {
        $this->tokens = new Lexer()->tokenize($source);
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

            if ($bracket === 0 && self::isForKeyword($token)) {
                break; // the keyword — LHS is done
            }

            $step = $token->groupDepthChange();
            $bracket += $step;

            if (! $token->isPunct(Token::PAREN_OPEN) && ! $token->isPunct(Token::PAREN_CLOSE)) {
                $destructure += $step; // a grouping `(item, index)` is not a pattern; `{…}`/`[…]` are
            }

            if ($step === 0 && $destructure === 0 && $token->isIdentifier()) {
                $aliases[] = $token->value;
            }

            $this->next();
        }

        return $aliases;
    }

    /**
     * Is this the keyword that separates a `v-for`'s aliases from the thing being iterated? `in` and
     * `of` are the only two Vue accepts, and both read the same way.
     */
    private static function isForKeyword(Lexeme $token): bool
    {
        return $token->isIdentifier('in') || $token->isIdentifier('of');
    }

    private function forKeyword(): string
    {
        $token = $this->peek();

        if (! self::isForKeyword($token)) {
            return 'in';
        }

        $this->next();

        return $token->value;
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
        while ($this->peek()->isIdentifier('as')) {
            $this->next();
            $this->skipTypeAnnotation();
        }

        while (true) {
            $token = $this->peek();
            $operator = $token->value;

            if (! $token->isPunct() || ! isset(self::PRECEDENCE[$operator]) || self::PRECEDENCE[$operator] < $minPrecedence) {
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

        if ($token->isPunct() && in_array($token->value, ['!', '-', '+'], true)) {
            $this->next();

            return new Expr(ExprKind::Unary, ['op' => $token->value, 'argument' => $this->unary()]);
        }

        if ($token->isIdentifier('typeof')) {
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

        while (! $this->isEof()) {
            $token = $this->peek();

            if ($token->isTypeCloser() && $depth === 0) {
                return; // a closing bracket belonging to the enclosing expression
            }

            if ($depth === 0 && $token->isPunct() && ! $token->isTypeOpener() && ! self::connectsType($token)) {
                return; // a top-level token that isn't a type connector ends the type
            }

            $depth += $token->typeDepthChange();
            $this->next();
        }
    }

    /**
     * Does this token CONTINUE a type rather than end it — the `.` of a qualified name and the
     * `|`/`&` of a union or intersection?
     */
    private static function connectsType(Lexeme $token): bool
    {
        return $token->isPunct('.') || $token->isPunct('|') || $token->isPunct('&');
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

        if ($token->isIdentifier()) {
            $this->next();

            if (in_array($token->value, ['true', 'false', 'null', 'undefined'], true)) {
                return new Expr(ExprKind::Literal, ['value' => $token->value, 'raw' => $token->value]);
            }

            if ($this->isPunct('=>')) {
                $this->next();
                $param = new Expr(ExprKind::Identifier, ['name' => $token->value]);

                return new Expr(ExprKind::Arrow, ['params' => [$param], 'body' => $this->expression()]);
            }

            return new Expr(ExprKind::Identifier, ['name' => $token->value]);
        }

        if ($token->is(Token::NUMBER)) {
            $this->next();

            return new Expr(ExprKind::Literal, ['value' => $token->value, 'raw' => $token->value]);
        }

        if ($token->is(Token::STRING)) {
            $this->next();

            return new Expr(ExprKind::Literal, ['value' => $this->unquote($token->value), 'raw' => $token->value]);
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
        if (! $token->isNone()) {
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

            if ($token->isGroupCloser() && $depth === 0) {
                return ($this->tokens[$i + 1] ?? Lexeme::none($i))->isPunct('=>');
            }

            $depth += $token->groupDepthChange();
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
            } elseif ($this->peek()->isIdentifier()) {
                $params[] = new Expr(ExprKind::Identifier, ['name' => $this->peek()->value]);
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

            if ($change === 0 && $this->peek()->isIdentifier()) {
                $names[] = $this->peek()->value;
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
            $key = $this->objectKey($this->peek());
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
     * The KEY an object-literal entry is written under — an identifier, a number, or a quoted string
     * read back without its quotes. None of those, and the entry is computed (`[expr]:`), which has
     * no name to record.
     */
    private function objectKey(Lexeme $token): ?string
    {
        return match (true) {
            $token->is(Token::STRING) => $this->unquote($token->value),
            $token->isIdentifier(), $token->is(Token::NUMBER) => $token->value,
            default => null,
        };
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

        if (! $token->isIdentifier()) {
            return '';
        }

        $this->next();

        return $token->value;
    }

    private function skipArrowMarker(): void
    {
        if ($this->isPunct('=>')) {
            $this->next();
        }
    }

    // ---- token cursor ---------------------------------------------------------

    private function peek(): Lexeme
    {
        return $this->tokens[$this->pos] ?? Lexeme::none($this->pos);
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
        return $this->peek()->groupDepthChange();
    }

    private function isPunct(string $value): bool
    {
        return $this->peek()->isPunct($value);
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
        return $this->peek()->isNone();
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
}
