<?php

namespace Shop\Providers;

use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\OrphanedBinding;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Services\PaymentGatewayRegistry;

class ShopServiceProvider extends ServiceProvider
{
    /**
     * Righteous: PaymentProcessor resolves this registry, so the wiring answers a real demand.
     */
    #[Righteous(OrphanedBinding::class)]
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayRegistry::class);
    }
}
