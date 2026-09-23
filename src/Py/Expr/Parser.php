<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Expr;

use JesseGall\CodeCommandments\Py\Cursor;
use JesseGall\CodeCommandments\Py\Lexer;
use JesseGall\CodeCommandments\Py\Token;
use JesseGall\CodeCommandments\Py\TokenKind;

/**
 * Reads Python expressions off a {@see Cursor}, climbing Python's precedence from `lambda` and the
 * conditional down through `or`, `and`, `not`, comparisons, the bitwise and arithmetic operators and
 * `**` to the atoms, with attributes, subscripts and calls as trailers. Shares its cursor with the
 * statement parser, which hands it every expression a statement holds. Total: what it cannot read
 * becomes an {@see ExprKind::Unknown} node, never an exception.
 */
final class Parser
{
    /**
     * The binary operator levels between comparison and `**`, loosest first.
     */
    private const array BINARY_LEVELS = [['|'], ['^'], ['&'], ['<<', '>>'], ['+', '-'], ['*', '@', '/', '//', '%']];

    private const array COMPARISON_OPERATORS = ['<', '>', '==', '>=', '<=', '!='];

    private const array PREFIX_OPERATORS = ['+', '-', '~'];

    /**
     * Keywords that can never begin an expression — where a list of expressions stops.
     */
    private const array NOT_EXPRESSION_STARTS = [
        'in', 'for', 'if', 'else', 'elif', 'from', 'as', 'import', 'def', 'class', 'return', 'and', 'or',
        'is', 'with', 'while', 'try', 'except', 'finally', 'raise', 'pass', 'break', 'continue', 'del',
        'global', 'nonlocal', 'assert',
    ];

    private const array OPENERS = ['(', '[', '{', '-', '+', '~', '*', '**', '...'];

    public function __construct(private readonly Cursor $cursor) {}

    /**
     * The expression (a tuple, when it has commas) that $source holds, placed at $baseOffset in its file.
     */
    public static function parse(string $source, int $baseOffset = 0): Expr
    {
        return new self(new Cursor(new Lexer()->tokenize($source), $baseOffset))->expressionList();
    }

    /**
     * One expression or several separated by commas — a tuple then, trailing comma allowed: what a
     * `return`, an assignment's value or an expression statement holds.
     */
    public function expressionList(): Expr
    {
        return $this->commaList($this->starOrExpression(...));
    }

    /**
     * The names a `for` binds or a `del` removes — bitwise-level expressions, so `in` is left to the
     * statement: `for k, v in …`.
     */
    public function targetList(): Expr
    {
        return $this->commaList($this->starredTarget(...));
    }

    /**
     * One target — what a `with … as` binds, a single name or attribute or a parenthesised tuple.
     */
    public function target(): Expr
    {
        return $this->starredTarget();
    }

    /**
     * A `case` pattern — read below the conditional expression, so the `if` after it is left for the
     * case's guard: `case Move(x, y) if x > 0:`.
     */
    public function patternList(): Expr
    {
        return $this->commaList($this->orTest(...));
    }

    /**
     * One $element, or several separated by commas — a tuple then, a trailing comma allowed.
     *
     * @param  callable(): Expr  $element
     */
    private function commaList(callable $element): Expr
    {
        $start = $this->cursor->offset();
        $first = $element();

        if (! $this->cursor->atOp(',')) {
            return $first;
        }

        $elements = [$first];

        while ($this->cursor->advanceIfOp(',') && $this->isAtExpression()) {
            $elements[] = $element();
        }

        return $this->located(ExprKind::Tuple, ['elements' => $elements], $start);
    }

    /**
     * An expression, a walrus binding included — `(n := len(rows))`.
     */
    public function expression(): Expr
    {
        if ($this->cursor->peek()->isName() && $this->cursor->at(1)->isOp(':=')) {
            $start = $this->cursor->offset();
            $target = $this->name();
            $this->cursor->advance();

            return $this->located(ExprKind::Walrus, ['target' => $target, 'value' => $this->expression()], $start);
        }

        return $this->test();
    }

    /**
     * An expression at the level a condition or a default is written: a lambda, or a disjunction that
     * may be the value of a conditional expression.
     */
    public function test(): Expr
    {
        if ($this->cursor->atName('lambda')) {
            return $this->lambda();
        }

        $start = $this->cursor->offset();
        $body = $this->orTest();

        if (! $this->cursor->advanceIfName('if')) {
            return $body;
        }

        $test = $this->orTest();
        $this->cursor->advanceIfName('else');

        return $this->located(ExprKind::Conditional, ['test' => $test, 'then' => $body, 'else' => $this->test()], $start);
    }

    /**
     * Can an expression begin at the token under the cursor?
     */
    public function isAtExpression(): bool
    {
        $token = $this->cursor->peek();

        return match ($token->kind) {
            TokenKind::Name => ! in_array($token->value, self::NOT_EXPRESSION_STARTS, true),
            TokenKind::Number, TokenKind::String => true,
            TokenKind::Op => in_array($token->value, self::OPENERS, true),
            TokenKind::Newline, TokenKind::Indent, TokenKind::Dedent, TokenKind::Comment, TokenKind::EndMarker => false,
        };
    }

    private function starOrExpression(): Expr
    {
        if (! $this->cursor->atOp('*')) {
            return $this->expression();
        }

        $start = $this->cursor->offset();
        $this->cursor->advance();

        return $this->located(ExprKind::Starred, ['value' => $this->bitwise(0), 'double' => false], $start);
    }

    private function starredTarget(): Expr
    {
        if (! $this->cursor->atOp('*')) {
            return $this->bitwise(0);
        }

        $start = $this->cursor->offset();
        $this->cursor->advance();

        return $this->located(ExprKind::Starred, ['value' => $this->bitwise(0), 'double' => false], $start);
    }

    private function lambda(): Expr
    {
        $start = $this->cursor->offset();
        $this->cursor->advance(); // `lambda`
        $params = [];

        while (! $this->cursor->atOp(':') && ! $this->isAtLineEnd()) {
            if ($this->atAnyOp(['*', '**'])) {
                $this->cursor->advance();
            }

            if ($this->cursor->peek()->isName()) {
                $params[] = $this->name();
            }

            if ($this->cursor->advanceIfOp('=')) {
                $this->test();
            }

            if (! $this->cursor->advanceIfOp(',')) {
                break;
            }
        }

        $this->cursor->advanceIfOp(':');

        return $this->located(ExprKind::Lambda, ['params' => $params, 'body' => $this->test()], $start);
    }

    private function orTest(): Expr
    {
        return $this->logical('or', $this->andTest(...));
    }

    private function andTest(): Expr
    {
        return $this->logical('and', $this->notTest(...));
    }

    /**
     * $operand joined by the word operator $operator, left to right.
     *
     * @param  callable(): Expr  $operand
     */
    private function logical(string $operator, callable $operand): Expr
    {
        $start = $this->cursor->offset();
        $left = $operand();

        while ($this->cursor->advanceIfName($operator)) {
            $left = $this->located(ExprKind::Binary, ['op' => $operator, 'left' => $left, 'right' => $operand()], $start);
        }

        return $left;
    }

    private function notTest(): Expr
    {
        if (! $this->cursor->atName('not')) {
            return $this->comparison();
        }

        $start = $this->cursor->offset();
        $this->cursor->advance();

        return $this->located(ExprKind::Unary, ['op' => 'not', 'operand' => $this->notTest()], $start);
    }

    private function comparison(): Expr
    {
        $start = $this->cursor->offset();
        $operands = [$this->bitwise(0)];
        $operators = [];

        while (($operator = $this->comparisonOperator()) !== null) {
            $operators[] = $operator;
            $operands[] = $this->bitwise(0);
        }

        return $operators === []
            ? $operands[0]
            : $this->located(ExprKind::Compare, ['operators' => $operators, 'operands' => $operands], $start);
    }

    /**
     * The comparison operator under the cursor, consumed — `is not` and `not in` as one — or null.
     */
    private function comparisonOperator(): ?string
    {
        $token = $this->cursor->peek();

        $operator = match (true) {
            $token->kind === TokenKind::Op && in_array($token->value, self::COMPARISON_OPERATORS, true) => $token->value,
            $token->isName('in') => 'in',
            $token->isName('is') => $this->cursor->at(1)->isName('not') ? 'is not' : 'is',
            $token->isName('not') && $this->cursor->at(1)->isName('in') => 'not in',
            default => null,
        };

        if ($operator === null) {
            return null;
        }

        for ($words = substr_count($operator, ' ') + 1; $words > 0; $words--) {
            $this->cursor->advance();
        }

        return $operator;
    }

    /**
     * The binary operators from `|` down to `*`, one level per entry of {@see BINARY_LEVELS}, each
     * left-associative; below the last sits the unary level.
     */
    private function bitwise(int $level): Expr
    {
        if ($level === count(self::BINARY_LEVELS)) {
            return $this->factor();
        }

        $start = $this->cursor->offset();
        $left = $this->bitwise($level + 1);

        while ($this->atAnyOp(self::BINARY_LEVELS[$level])) {
            $operator = $this->cursor->advance()->value;
            $left = $this->located(ExprKind::Binary, ['op' => $operator, 'left' => $left, 'right' => $this->bitwise($level + 1)], $start);
        }

        return $left;
    }

    private function factor(): Expr
    {
        if (! $this->atAnyOp(self::PREFIX_OPERATORS)) {
            return $this->power();
        }

        $start = $this->cursor->offset();
        $operator = $this->cursor->advance()->value;

        return $this->located(ExprKind::Unary, ['op' => $operator, 'operand' => $this->factor()], $start);
    }

    /**
     * `**` binds tighter than a sign on its left and is right-associative: `-x ** 2` is `-(x ** 2)`.
     */
    private function power(): Expr
    {
        $start = $this->cursor->offset();
        $base = $this->awaited();

        if (! $this->cursor->advanceIfOp('**')) {
            return $base;
        }

        return $this->located(ExprKind::Binary, ['op' => '**', 'left' => $base, 'right' => $this->factor()], $start);
    }

    private function awaited(): Expr
    {
        if (! $this->cursor->atName('await')) {
            return $this->primary();
        }

        $start = $this->cursor->offset();
        $this->cursor->advance();

        return $this->located(ExprKind::Unary, ['op' => 'await', 'operand' => $this->primary()], $start);
    }

    /**
     * An atom and its trailers — `.name`, `[index]`, `(arguments)` — applied left to right.
     */
    private function primary(): Expr
    {
        $start = $this->cursor->offset();
        $node = $this->atom();

        while ($this->isAtTrailer()) {
            $node = match (true) {
                $this->cursor->atDottedName() => $this->attribute($node, $start),
                $this->cursor->atOp('[') => $this->subscript($node, $start),
                default => $this->call($node, $start),
            };
        }

        if ($this->cursor->atOp('.')) {
            $this->cursor->advance(); // `rows[0].` cut off mid-trailer
        }

        return $node;
    }

    /**
     * Does a trailer follow — `.name`, `[index]` or `(arguments)`?
     */
    private function isAtTrailer(): bool
    {
        return $this->cursor->atDottedName() || $this->cursor->atOp('[') || $this->cursor->atOp('(');
    }

    private function attribute(Expr $object, int $start): Expr
    {
        $this->cursor->advance(); // `.`

        return $this->located(ExprKind::Attribute, ['object' => $object, 'name' => $this->cursor->advance()->value], $start);
    }

    private function subscript(Expr $object, int $start): Expr
    {
        $this->cursor->advance(); // `[`
        $indexStart = $this->cursor->offset();
        $items = [$this->sliceItem()];

        while ($this->cursor->advanceIfOp(',') && ! $this->cursor->atOp(']')) {
            $items[] = $this->sliceItem();
        }

        $index = count($items) === 1 && ! $this->precededByComma() ? $items[0] : $this->located(ExprKind::Tuple, ['elements' => $items], $indexStart);
        $this->cursor->advanceIfOp(']');

        return $this->located(ExprKind::Subscript, ['object' => $object, 'index' => $index], $start);
    }

    /**
     * One item of a subscript: an expression, or a slice `lower:upper:step` with any part left out.
     */
    private function sliceItem(): Expr
    {
        $start = $this->cursor->offset();
        $lower = $this->cursor->atOp(':') ? null : $this->expression();

        if (! $this->cursor->advanceIfOp(':')) {
            return $lower ?? $this->unknown();
        }

        $upper = $this->isAtExpression() ? $this->expression() : null;
        $step = $this->cursor->advanceIfOp(':') && $this->isAtExpression() ? $this->expression() : null;

        return $this->located(ExprKind::Slice, ['lower' => $lower, 'upper' => $upper, 'step' => $step], $start);
    }

    /**
     * Does a comma under the cursor lead to one more element of a display closed by $closer? The comma
     * is consumed; a trailing one before the closer leads to none.
     */
    private function isAnotherElementBefore(string $closer): bool
    {
        return $this->cursor->advanceIfOp(',') && $this->cursor->isBefore($closer);
    }

    private function precededByComma(): bool
    {
        $mark = $this->cursor->mark();
        $this->cursor->rewind($mark - 1);
        $comma = $this->cursor->atOp(',');
        $this->cursor->rewind($mark);

        return $comma;
    }

    private function call(Expr $callee, int $start): Expr
    {
        return $this->located(ExprKind::Call, ['callee' => $callee, 'arguments' => $this->callArguments()], $start);
    }

    /**
     * The arguments in the parentheses under the cursor — a call's, or a class's bases.
     *
     * @return list<Expr>
     */
    public function callArguments(): array
    {
        $this->cursor->advance(); // `(`
        $arguments = [];

        while ($this->cursor->isBefore(')')) {
            $arguments[] = $this->argument();

            if (! $this->cursor->advanceIfOp(',')) {
                break;
            }
        }

        $this->cursor->advanceIfOp(')');

        return $arguments;
    }

    /**
     * One call argument: `*rest`, `**options`, `name=value`, a generator written bare as the only
     * argument (`sum(r.total for r in rows)`), or a plain expression.
     */
    private function argument(): Expr
    {
        $start = $this->cursor->offset();

        if ($this->cursor->atOp('*') || $this->cursor->atOp('**')) {
            $double = $this->cursor->advance()->value === '**';

            return $this->located(ExprKind::Starred, ['value' => $this->test(), 'double' => $double], $start);
        }

        if ($this->cursor->peek()->isName() && $this->cursor->at(1)->isOp('=')) {
            $name = $this->cursor->advance()->value;
            $this->cursor->advance(); // `=`

            return $this->located(ExprKind::Keyword, ['name' => $name, 'value' => $this->test()], $start);
        }

        $value = $this->expression();

        return $this->atComprehension() ? $this->comprehension('generator', null, $value, $start) : $value;
    }

    private function atom(): Expr
    {
        $token = $this->cursor->peek();

        return match (true) {
            $token->isName('True'), $token->isName('False') => $this->literal('bool'),
            $token->isName('None') => $this->literal('none'),
            $token->isName('yield') => $this->yield(),
            $token->isName('lambda') => $this->lambda(),
            $token->isName() && ! in_array($token->value, self::NOT_EXPRESSION_STARTS, true) => $this->name(),
            $token->is(TokenKind::Number) => $this->literal('number'),
            $token->is(TokenKind::String) => $this->strings(),
            $token->isOp('...') => $this->literal('ellipsis'),
            $token->isOp('(') => $this->parenthesised(),
            $token->isOp('[') => $this->bracketed(),
            $token->isOp('{') => $this->braced(),
            default => $this->unknown(),
        };
    }

    private function name(): Expr
    {
        $start = $this->cursor->offset();

        return $this->located(ExprKind::Name, ['name' => $this->cursor->advance()->value], $start);
    }

    private function literal(string $type): Expr
    {
        $start = $this->cursor->offset();

        return $this->located(ExprKind::Literal, ['type' => $type, 'value' => $this->cursor->advance()->value], $start);
    }

    /**
     * One string, or several written side by side — implicit concatenation, one value. Any of them
     * formatted makes the whole an f-string.
     */
    private function strings(): Expr
    {
        $start = $this->cursor->offset();
        $parts = [];
        $offsets = [];

        while ($this->cursor->peek()->is(TokenKind::String)) {
            $offsets[] = $this->cursor->offset();
            $parts[] = $this->cursor->advance();
        }

        $prefixes = strtolower(implode('', array_map(static fn (Token $part) => self::prefixOf($part->value), $parts)));

        if (str_contains($prefixes, 'f')) {
            return $this->located(ExprKind::FString, ['parts' => array_merge(...array_map($this->stringParts(...), $parts, $offsets))], $start);
        }

        return $this->located(ExprKind::Literal, [
            'type' => str_contains($prefixes, 'b') ? 'bytes' : 'string',
            'value' => implode('', array_map(static fn (Token $part) => self::contentOf($part->value), $parts)),
        ], $start);
    }

    /**
     * The parts one string of an f-string concatenation contributes — an f-string's text and fields, or
     * a plain string's content as one text part.
     *
     * @return list<Expr>
     */
    private function stringParts(Token $string, int $offset): array
    {
        if (str_contains(strtolower(self::prefixOf($string->value)), 'f')) {
            return FStringReader::parts($string->value, $offset);
        }

        return [new Expr(ExprKind::Literal, ['type' => 'string', 'value' => self::contentOf($string->value)])->locatedAt($offset, $offset + strlen($string->value))];
    }

    /**
     * The letters a string literal opens with — `rb` of `rb"…"`.
     */
    private static function prefixOf(string $literal): string
    {
        return substr($literal, 0, strcspn($literal, '\'"'));
    }

    /**
     * What a string literal holds between its quotes, escapes left as written.
     */
    private static function contentOf(string $literal): string
    {
        $body = substr($literal, strlen(self::prefixOf($literal)));
        $quote = str_starts_with($body, '"""') || str_starts_with($body, "'''") ? substr($body, 0, 3) : substr($body, 0, 1);
        $closed = str_ends_with($body, $quote) && strlen($body) >= 2 * strlen($quote);

        return substr($body, strlen($quote), strlen($body) - strlen($quote) - ($closed ? strlen($quote) : 0));
    }

    /**
     * `(…)` — an empty tuple, a yield, a generator, a tuple, or a grouped expression returned as itself.
     */
    private function parenthesised(): Expr
    {
        $start = $this->cursor->offset();
        $this->cursor->advance(); // `(`

        if ($this->cursor->advanceIfOp(')')) {
            return $this->located(ExprKind::Tuple, ['elements' => []], $start);
        }

        if ($this->cursor->atName('yield')) {
            $yield = $this->yield();
            $this->cursor->advanceIfOp(')');

            return $yield;
        }

        $first = $this->starOrExpression();

        if ($this->atComprehension()) {
            $generator = $this->comprehension('generator', null, $first, $start);
            $this->cursor->advanceIfOp(')');

            return $generator;
        }

        if (! $this->cursor->atOp(',')) {
            $this->cursor->advanceIfOp(')');

            return $first;
        }

        $elements = [$first];

        while ($this->isAnotherElementBefore(')')) {
            $elements[] = $this->starOrExpression();
        }

        $this->cursor->advanceIfOp(')');

        return $this->located(ExprKind::Tuple, ['elements' => $elements], $start);
    }

    /**
     * `[…]` — a list, or a list comprehension.
     */
    private function bracketed(): Expr
    {
        $start = $this->cursor->offset();
        $this->cursor->advance(); // `[`

        if ($this->cursor->advanceIfOp(']')) {
            return $this->located(ExprKind::List, ['elements' => []], $start);
        }

        $first = $this->starOrExpression();

        if ($this->atComprehension()) {
            $list = $this->comprehension('list', null, $first, $start);
            $this->cursor->advanceIfOp(']');

            return $list;
        }

        $elements = [$first];

        while ($this->isAnotherElementBefore(']')) {
            $elements[] = $this->starOrExpression();
        }

        $this->cursor->advanceIfOp(']');

        return $this->located(ExprKind::List, ['elements' => $elements], $start);
    }

    /**
     * `{…}` — a dict or a set, or a comprehension of either; `{}` is an empty dict.
     */
    private function braced(): Expr
    {
        $start = $this->cursor->offset();
        $this->cursor->advance(); // `{`

        if ($this->cursor->advanceIfOp('}')) {
            return $this->located(ExprKind::Dict, ['keys' => [], 'values' => []], $start);
        }

        if ($this->cursor->atOp('**')) {
            return $this->dict($start, null, $this->doubleStarred());
        }

        $first = $this->starOrExpression();

        if ($this->cursor->advanceIfOp(':')) {
            $value = $this->expression();

            if (! $this->atComprehension()) {
                return $this->dict($start, $first, $value);
            }

            $dict = $this->comprehension('dict', $first, $value, $start);
            $this->cursor->advanceIfOp('}');

            return $dict;
        }

        if ($this->atComprehension()) {
            $set = $this->comprehension('set', null, $first, $start);
            $this->cursor->advanceIfOp('}');

            return $set;
        }

        $elements = [$first];

        while ($this->isAnotherElementBefore('}')) {
            $elements[] = $this->starOrExpression();
        }

        $this->cursor->advanceIfOp('}');

        return $this->located(ExprKind::Set, ['elements' => $elements], $start);
    }

    /**
     * The rest of a dict display whose first entry is already read — a `**unpacked` one when $key is null.
     */
    private function dict(int $start, ?Expr $key, Expr $value): Expr
    {
        $keys = [$key];
        $values = [$value];

        while ($this->isAnotherElementBefore('}')) {
            if ($this->cursor->atOp('**')) {
                $keys[] = null;
                $values[] = $this->doubleStarred();

                continue;
            }

            $keys[] = $this->expression();
            $this->cursor->advanceIfOp(':');
            $values[] = $this->expression();
        }

        $this->cursor->advanceIfOp('}');

        return $this->located(ExprKind::Dict, ['keys' => $keys, 'values' => $values], $start);
    }

    private function doubleStarred(): Expr
    {
        $start = $this->cursor->offset();
        $this->cursor->advance(); // `**`

        return $this->located(ExprKind::Starred, ['value' => $this->bitwise(0), 'double' => true], $start);
    }

    private function atComprehension(): bool
    {
        return $this->cursor->atName('for') || ($this->cursor->atName('async') && $this->cursor->at(1)->isName('for'));
    }

    /**
     * The `for … in … if …` clauses after a comprehension's element, and the comprehension they make.
     * An iterable and a condition are read below the conditional, so a clause's `if` is never taken
     * for a conditional expression.
     */
    private function comprehension(string $of, ?Expr $key, Expr $element, int $start): Expr
    {
        $clauses = [];

        while ($this->atComprehension()) {
            $clauseStart = $this->cursor->offset();
            $async = $this->cursor->advanceIfName('async');
            $this->cursor->advance(); // `for`
            $target = $this->targetList();
            $this->cursor->advanceIfName('in');
            $iterable = $this->orTest();
            $conditions = [];

            while ($this->cursor->advanceIfName('if')) {
                $conditions[] = $this->orTest();
            }

            $clauses[] = $this->located(ExprKind::ComprehensionFor, ['target' => $target, 'iterable' => $iterable, 'conditions' => $conditions, 'async' => $async], $clauseStart);
        }

        return $this->located(ExprKind::Comprehension, ['of' => $of, 'key' => $key, 'element' => $element, 'clauses' => $clauses], $start);
    }

    private function yield(): Expr
    {
        $start = $this->cursor->offset();
        $this->cursor->advance(); // `yield`

        if ($this->cursor->advanceIfName('from')) {
            return $this->located(ExprKind::Yield, ['value' => $this->test(), 'from' => true], $start);
        }

        return $this->located(ExprKind::Yield, ['value' => $this->isAtExpression() ? $this->expressionList() : null, 'from' => false], $start);
    }

    /**
     * Syntax the grammar does not model. One token is stepped over so the parse advances — unless it
     * closes a bracket or ends the line, which belongs to whoever opened it.
     */
    private function unknown(): Expr
    {
        $start = $this->cursor->offset();

        if (! $this->isAtLineEnd() && ! $this->atAnyOp([')', ']', '}', ',', ':'])) {
            $this->cursor->advance();
        }

        return $this->located(ExprKind::Unknown, [], $start);
    }

    private function isAtLineEnd(): bool
    {
        return in_array($this->cursor->peek()->kind, [TokenKind::Newline, TokenKind::EndMarker, TokenKind::Indent, TokenKind::Dedent], true);
    }

    /**
     * @param  list<string>  $operators
     */
    private function atAnyOp(array $operators): bool
    {
        $token = $this->cursor->peek();

        return $token->kind === TokenKind::Op && in_array($token->value, $operators, true);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function located(ExprKind $kind, array $props, int $start): Expr
    {
        return new Expr($kind, $props)->locatedAt($start, max($start, $this->cursor->consumedEnd()));
    }
}
