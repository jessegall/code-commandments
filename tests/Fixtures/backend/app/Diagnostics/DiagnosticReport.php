<?php

namespace Shop\Diagnostics;

use JesseGall\CodeCommandments\Sins\Backend\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Telemetry\Meter;

/**
 * What the operator reads after a diagnostics pass.
 */
final class DiagnosticReport
{
    #[Sinful(ConcreteCrossModuleDependency::class)]
    public function __construct(private readonly Meter $meter) {}

    public function line(): string
    {
        return sprintf('ticks=%d', $this->meter->tick());
    }
}
