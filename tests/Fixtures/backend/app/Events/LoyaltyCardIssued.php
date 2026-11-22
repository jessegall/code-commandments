<?php

namespace Shop\Events;

/**
 * A loyalty card was issued, raised by the card model itself when it is created.
 */
final readonly class LoyaltyCardIssued
{
    public function __construct(public int $card) {}
}
