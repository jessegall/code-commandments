<?php

namespace Shop\Market;

use Illuminate\Support\ServiceProvider;

class MarketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'market');
    }
}
