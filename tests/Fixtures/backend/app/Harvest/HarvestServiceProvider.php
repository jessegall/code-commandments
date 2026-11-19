<?php

namespace Shop\Harvest;

use Illuminate\Support\ServiceProvider;

class HarvestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'harvest');
    }
}
