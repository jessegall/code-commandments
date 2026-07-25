<?php

namespace Shop\Diagnostics;

use Shop\Telemetry\Meter;

/**
 * What the operator reads after a diagnostics pass.
 */
final class DiagnosticReport
{
    public function __construct(private readonly Meter $meter) {}

    public function line(): string
    {
        return sprintf('ticks=%d', $this->meter->tick());
    }
}
