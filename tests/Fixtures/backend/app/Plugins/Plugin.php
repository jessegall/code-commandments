<?php

namespace Shop\Plugins;

use JesseGall\CodeCommandments\Sins\Backend\CapabilityProbeHub;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * A shop plugin: it answers every contribution itself, none by default.
 */
#[Fixed(CapabilityProbeHub::class)]
abstract class Plugin
{
    public function widgetCount(): int
    {
        return 0;
    }

    public function routeCount(): int
    {
        return 0;
    }

    public function menuCount(): int
    {
        return 0;
    }
}
