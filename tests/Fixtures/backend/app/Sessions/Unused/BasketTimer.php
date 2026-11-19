<?php

namespace Shop\Sessions\Unused;

use JesseGall\CodeCommandments\Sins\Backend\UnusedPrivateMember;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Times how long a basket stays open; it still writes a start time nothing ever reads.
 */
#[Sinful(UnusedPrivateMember::class)]
final class BasketTimer
{
    private int $startedAt = 0;

    private int $touchedAt = 0;

    public function open(int $now): void
    {
        $this->startedAt = $now;
        $this->touchedAt = $now;
    }

    public function touch(int $now): void
    {
        $this->touchedAt = $now;
    }

    public function idleFor(int $now): int
    {
        return $now - $this->touchedAt;
    }
}
