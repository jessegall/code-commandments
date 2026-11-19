<?php

namespace Shop\Tills;

use Illuminate\Container\Attributes\Scoped;

/**
 * The till the request or the shift being closed works at: one per scope, so a new scope starts at no till.
 */
#[Scoped]
final class CurrentTill
{
    private int $till = 0;

    public function open(int $till): void
    {
        $this->till = $till;
    }

    public function number(): int
    {
        return $this->till;
    }
}
