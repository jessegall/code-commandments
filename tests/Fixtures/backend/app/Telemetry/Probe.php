<?php

namespace Shop\Telemetry;

// @sin NamespaceCycle
use Shop\Diagnostics\DiagnosticRun;

/**
 * Inheritance welds the two namespaces harder than any call could: Telemetry cannot even be loaded
 * without Diagnostics, while Diagnostics reads Telemetry from two of its own classes.
 */
final class Probe extends DiagnosticRun
{
    public function sample(): float
    {
        return 0.5;
    }
}
