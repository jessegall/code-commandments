<?php

namespace Shop\Kiosk;

/**
 * One customer's time at a kiosk: the token the screen holds and the minutes left on it.
 */
final readonly class KioskSession
{
    public function __construct(public string $token, public int $minutes) {}

    public function isSpent(): bool
    {
        return $this->minutes <= 0;
    }
}
