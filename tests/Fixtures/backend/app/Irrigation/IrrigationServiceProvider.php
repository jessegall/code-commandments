<?php

namespace Shop\Irrigation;

use Illuminate\Support\ServiceProvider;

class IrrigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'irrigation');
    }
}
