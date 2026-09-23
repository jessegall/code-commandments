<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * A `match subject:` with its cases.
 */
final class MatchStmt extends Node
{
    /**
     * @param  list<MatchCase>  $cases
     */
    public function __construct(
        public readonly Expr $subject,
        public readonly array $cases,
    ) {}

    public function children(): array
    {
        return $this->cases;
    }

    public function expressions(): array
    {
        return [$this->subject];
    }

    public function isBranchingConstruct(): bool
    {
        return true;
    }

    /**
     * The literal every `case` tests for, as comparable keys — empty unless every case other than the
     * `_` wildcard is literals alone.
     *
     * @return list<string>
     */
    public function literalCaseKeys(): array
    {
        $alternatives = array_merge([], ...array_map(static fn (MatchCase $case): array => $case->pattern->alternatives(), $this->cases));
        $tested = array_filter($alternatives, static fn (Expr $pattern): bool => $pattern->dottedName() !== '_');
        $keys = array_map(static fn (Expr $pattern): string => $pattern->literalKey(), array_values($tested));

        return in_array('', $keys, true) ? [] : $keys;
    }
}
