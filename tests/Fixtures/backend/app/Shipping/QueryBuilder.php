<?php

namespace Shop\Shipping;

/**
 * A fluent query builder — every narrowing hop hands the SAME builder back, which is what makes a
 * chain over it structurally indistinguishable from a keyed fetch until you ask WHO the call is made
 * on ({@see ConsignmentLedger}).
 */
final class QueryBuilder
{
    public function join(string $table, string $left, string $operator, string $right): self
    {
        return $this;
    }

    public function where(string $column, string $value): self
    {
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        return $this;
    }

    public function exists(): bool
    {
        return true;
    }
}
