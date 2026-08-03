<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ServiceLocationInPageObject;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\Hidden;
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

/**
 * The FIX for the same status page: the health service is pulled through the container declaratively —
 * `#[Hidden] #[FromContainer(ContainersService::class)]` on a promoted property — so the getter reads an
 * injected collaborator instead of reaching out with `app()`.
 */
#[TypeScript]
#[Fixed(ServiceLocationInPageObject::class)]
final class InjectedStatusPage extends Data
{
    public function __construct(
        public readonly StatCard $uptime,
        public readonly StatCard $load,
        public readonly MenuLink $refresh,

        #[Hidden]
        #[FromContainer(ContainersService::class)]
        public readonly ContainersService $containers,
    ) {}

    public function isHealthy(): bool
    {
        return $this->containers->healthy();
    }
}
