<?php

namespace Shop\Billing;

/**
 * A store gift card and the balance left on it.
 */
final class GiftCard
{
    public function __construct(private readonly int $cents) {}

    public function remainingCents(): int
    {
        return $this->cents;
    }
}
