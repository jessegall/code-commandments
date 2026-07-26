<?php

namespace Shop\Providers;

use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\OrphanedBinding;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Integrations\CourierApi;
use Shop\Integrations\CourierRegistry;

/**
 * The registry pattern, where the ONLY reference to the registry lives inside a SIBLING binding's
 * factory: config names a courier, and the courier binding resolves the registry to turn that name
 * into an implementation. The wiring is load-bearing — delete the registry and every CourierApi
 * resolution fails — even though nothing outside `register()` ever names it. Only a reference inside
 * the registration of the SAME abstract proves nothing about demand.
 */
class CourierServiceProvider extends ServiceProvider
{
    #[Righteous(OrphanedBinding::class)]
    public function register(): void
    {
        $this->app->singleton(CourierRegistry::class, static fn () => new CourierRegistry);

        $this->app->bind(CourierApi::class, static fn ($app): CourierApi => $app->make(CourierRegistry::class)->preferred());
    }
}
