<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use Closure;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\StructuralHash;
use JesseGall\PhpTypes\Option;

/**
 * The statements a compound statement owns — the indented suite after its `:`, or the simple
 * statements written on that same line.
 */
final class Block extends Node
{
    /**
     * @param  list<Node>  $body
     */
    public function __construct(public readonly array $body = []) {}

    public function children(): array
    {
        return $this->body;
    }

    /**
     * The statements of this block, a string left standing on its own — a docstring — aside.
     *
     * @return list<Node>
     */
    public function statementsBeyondText(): array
    {
        return array_values(array_filter($this->body, static fn (Node $statement): bool => ! ($statement instanceof ExprStmt && $statement->isBareString())));
    }

    /**
     * The docstring this block opens with — the text of a string standing alone as its first statement.
     *
     * @return Option<string>
     */
    public function docstring(): Option
    {
        $first = $this->body[0] ?? null;

        return $first instanceof ExprStmt && $first->isBareString() ? Option::some((string) $first->value->get('value')) : Option::none();
    }

    /**
     * Is this block one two-way choice $tests accepts — a lone `if`/`else` or `return a if … else b`, or an
     * `if` whose arm returns followed by the one statement that is the other arm? Text above it is a
     * docstring, not work.
     */
    public function isTwoWayBranch(Closure $tests): bool
    {
        $statements = $this->statementsBeyondText();

        if (count($statements) === 1) {
            return $statements[0]->isTwoWayBranch($tests);
        }

        return count($statements) === 2 && $statements[0]->isTwoWayBranchBefore($statements[1], $tests);
    }

    /**
     * Is this block one `return` of a call handed the value fingerprinted $subject — `return payload(shape)`?
     */
    public function translates(string $subject): bool
    {
        $statements = $this->statementsBeyondText();

        return count($statements) === 1 && $statements[0]->returnedValue()->isSomeAnd(
            static fn (Expr $value): bool => $value->isCall() && array_any($value->get('arguments'), static fn (Expr $argument): bool => StructuralHash::ofExpression($argument) === $subject),
        );
    }

    /**
     * Does this block end by leaving — a `return`, a `raise`, a `continue` or a `break`?
     */
    public function hasTrailingExit(): bool
    {
        return $this->body !== [] && $this->body[array_key_last($this->body)]->isBailOut();
    }

    /**
     * Does this block DO something, rather than hand back a value or nothing? An arm that only returns a
     * literal or a name maps a choice to a value, and one that passes does no work at all.
     */
    public function doesWork(): bool
    {
        if (count($this->body) !== 1) {
            return $this->body !== [];
        }

        $only = $this->body[0];

        return ! $only->isNoOp() && ! $only->returnedValue()->isSomeAnd(static fn (Expr $value): bool => $value->isBareValue());
    }
}
