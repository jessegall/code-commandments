<?php

namespace Shop\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadEventWiring;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Events\PriceChanged;
use Shop\Events\StockReconciled;

/**
 * Two registrations, and only their DISPATCHERS tell them apart. PriceChanged is still raised by the
 * pricing service; nothing has raised StockReconciled since the reconciliation job was deleted, so
 * that listener chain dead-ends while looking exactly as load-bearing as the one above it.
 */
class StockEventProvider extends ServiceProvider
{
    #[Fixed(DeadEventWiring::class)]
    #[Righteous(DeadEventWiring::class)]
    public function register(): void
    {
        Event::listen(PriceChanged::class, 'Shop\\Listeners\\RepricePublications');
    }

    #[Sinful(DeadEventWiring::class)]
    public function boot(): void
    {
        Event::listen(StockReconciled::class, 'Shop\\Listeners\\NotifyWarehouse');
    }
}
