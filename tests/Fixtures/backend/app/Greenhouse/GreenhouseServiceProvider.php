<?php

namespace Shop\Greenhouse;

use Illuminate\Support\ServiceProvider;

class GreenhouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'greenhouse');
    }
}
