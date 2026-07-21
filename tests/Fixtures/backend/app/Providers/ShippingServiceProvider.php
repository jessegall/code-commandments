<?php

namespace Shop\Providers;

use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\OrphanedBinding;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Shipping\CourierRates;
use Shop\Shipping\TableCourierRates;

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
}
