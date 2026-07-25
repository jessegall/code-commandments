<?php

namespace Shop\Telemetry;

/**
 * A running counter the probes feed.
 */
final class Meter
{
    private int $ticks = 0;

    public function tick(): int
    {
        return ++$this->ticks;
    }
}
