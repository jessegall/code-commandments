<?php

namespace Shop\Plugins;

use JesseGall\CodeCommandments\Sins\Backend\CapabilityProbeHub;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * One installed shop plugin, and what it adds to the admin screens.
 */
#[Sinful(CapabilityProbeHub::class)]
final class PluginSlot
{
    public function __construct(private readonly object $plugin) {}

    public function widgetCount(): int
    {
        return $this->plugin instanceof ContributesWidgets ? $this->plugin->widgetCount() : 0;
    }

    public function routeCount(): int
    {
        return $this->plugin instanceof ContributesRoutes ? $this->plugin->routeCount() : 0;
    }

    public function menuCount(): int
    {
        return $this->plugin instanceof ContributesMenus ? $this->plugin->menuCount() : 0;
    }
}
