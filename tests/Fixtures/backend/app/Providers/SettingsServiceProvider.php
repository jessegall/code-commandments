<?php

namespace Shop\Providers;

use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadConfigKey;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The live readers. A provider is the composition root, so reading `config()` here is the idiom — and
 * every key named here stays alive. The keys NO reader names are the sin, over in `config/`.
 */
class SettingsServiceProvider extends ServiceProvider
{
    #[Righteous(DeadConfigKey::class)]
    public function register(): void
    {
        $this->app->singleton('shop.kiosk.timeout', static fn (): int => (int) config('kiosk.idle_timeout'));
        $this->app->singleton('shop.relay.heartbeat', static fn (): int => (int) config('relay.heartbeat_seconds'));
        $this->app->singleton('shop.stocktake.cycle', static fn (): int => (int) config('stocktake.cycle_days'));
    }
}
