<?php

namespace Shop\Bins;

use Shop\Giftwrap\Contracts\WrapStock;

/**
 * Counts the wrapping-paper rolls in the warehouse bins.
 */
final class BinWrapStock implements WrapStock
{
    public function __construct(private readonly int $rolls) {}

    public function rollsLeft(): int
    {
        return $this->rolls;
    }
}
