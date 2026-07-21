<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages\Tags;

use JesseGall\CodeCommandments\Packages\Exemption;

/**
 * Exemption tag: a Throwable raised purely to STEER CONTROL FLOW, never to report a failure — an
 * engine's `BreakSignal` ending a loop, a `StopSignal` unwinding to the runner. Catching one with an
 * empty body IS the semantics, so there is nothing to log; exempt from swallow-catch.
 */
final class ControlSignal extends Exemption
{
    public function slug(): string
    {
        return 'control-signal';
    }

    public function description(): string
    {
        return 'A Throwable thrown purely to steer control flow, not to report a failure (an engine\'s break/stop signal) — catching it with an empty body IS the semantics, so it is exempt from swallow-catch.';
    }
}
