<?php

namespace Shop\Providers;

use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\OrphanedBinding;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Shipping\CourierRates;
use Shop\Shipping\TableCourierRates;
use Shop\Shipping\ZoneTable;

/**
 * The courier this rates contract was written for is gone. The implementation still compiles, the
 * registration is guarded and deliberate-looking, and the whole thing is unreachable: nothing takes
 * a CourierRates and nothing resolves one.
 */
class ShippingServiceProvider extends ServiceProvider
{
    private bool $ratesEnabled = true;

    #[Sinful(OrphanedBinding::class)]
    public function boot(): void
    {
        if (! $this->ratesEnabled) {
            return;
        }

        $this->app->instance(CourierRates::class, new TableCourierRates());
    }

    /**
     * The rates registration went out with the courier that needed it. What is left is wiring a
     * consumer actually asks for: RegionCoverage type-hints a ZoneTable, so this binding answers a
     * resolve.
     */
    #[Fixed(OrphanedBinding::class)]
    public function register(): void
    {
        $this->app->singleton(ZoneTable::class);
    }
}
