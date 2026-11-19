<?php

namespace Shop\Giftwrap;

use Shop\Giftwrap\Contracts\WrapStock;

/**
 * Says whether the gift-wrap desk can still take orders today.
 */
final class WrapDesk
{
    public function __construct(private readonly WrapStock $stock) {}

    public function isOpen(): bool
    {
        return $this->stock->rollsLeft() > 0;
    }
}
