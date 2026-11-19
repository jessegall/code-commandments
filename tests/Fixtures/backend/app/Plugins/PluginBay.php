<?php

namespace Shop\Plugins;

use JesseGall\CodeCommandments\Sins\Backend\CapabilityProbeHub;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * One installed shop plugin, told for what it adds to the admin screens.
 */
#[Fixed(CapabilityProbeHub::class)]
final class PluginBay
{
    public function __construct(private readonly Plugin $plugin) {}

    public function additions(): int
    {
        return $this->plugin->widgetCount() + $this->plugin->routeCount() + $this->plugin->menuCount();
    }
}
