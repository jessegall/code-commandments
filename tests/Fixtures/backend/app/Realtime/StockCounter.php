<?php

namespace Shop\Realtime;

use JesseGall\CodeCommandments\Detectors\Backend\ConcurrentSubclassDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use JesseGall\Concurrent\Concurrent;

/**
 * Cross-process stock counter — again a subclass of the proxy rather than a plain
 * object handed out thread-safe by a factory.
 */
#[Sinful(ConcurrentSubclassDetector::class)]
final class StockCounter extends Concurrent
{
    public int $available = 0;

    public int $reserved = 0;

    public function reserve(int $quantity): void
    {
        $this->available -= $quantity;
        $this->reserved += $quantity;
    }

    public function release(int $quantity): void
    {
        $this->available += $quantity;
        $this->reserved -= $quantity;
    }
}
