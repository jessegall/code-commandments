<?php

namespace Shop\Providers;

use Illuminate\Support\ServiceProvider;
use Shop\Services\PaymentGatewayRegistry;

class ShopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayRegistry::class);
    }
}
