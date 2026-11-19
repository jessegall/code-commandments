<?php

namespace Shop\Providers;

use Illuminate\Support\ServiceProvider;
use Shop\Tills\CashierOnDuty;
use Shop\Tills\ReceiptHeaders;

/**
 * The tills' bindings: one receipt header printer for the run, and the cashier of each request.
 */
class TillServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReceiptHeaders::class);
        $this->app->scoped(CashierOnDuty::class, static fn () => new CashierOnDuty('front desk'));
    }
}
