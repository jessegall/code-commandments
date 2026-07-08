<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ServiceLocationInPageObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Smart Farmers SystemSettingsPage shape — slots promoted in the constructor, and a health check
 * that pulls a service straight out of the container instead of injecting it.
 */
#[TypeScript]
final class SystemStatusPage extends Data
{
    public function __construct(
        public readonly StatCard $uptime,
        public readonly StatCard $load,
        public readonly MenuLink $refresh,
    ) {}

    #[Sinful(ServiceLocationInPageObject::class)]
    #[Sinful(ContainerReach::class)]
    public function isHealthy(): bool
    {
        return app(ContainersService::class)->healthy();
    }

    public function status(): string
    {
        return match (true) {
            $this->load->value === '0' => 'idle',
            $this->uptime->value === '0' => 'starting',
            default => 'running',
        };
    }
}
