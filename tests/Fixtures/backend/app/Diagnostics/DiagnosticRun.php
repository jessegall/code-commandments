<?php

namespace Shop\Diagnostics;

use Shop\Telemetry\Meter;

/**
 * One pass of the diagnostics suite.
 */
class DiagnosticRun
{
    public function meter(): Meter
    {
        return new Meter();
    }
}
