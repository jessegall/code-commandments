<?php

namespace Shop\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\FacadeCall;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Services\StockReconciler;

/**
 * A queued job is a serialized MESSAGE, and the two halves of it are judged differently. `handle()` is
 * called by the container, so reaching for a facade there hides a dependency the signature could have
 * declared. `failed()` is called by the framework as `$command->failed($e)` — a fixed signature with no
 * injection seam at all, and a service could never have survived serialization onto the queue anyway.
 */
class ReconcileStockJob implements ShouldQueue
{
    #[Sinful(FacadeCall::class)]
    public function handle(): void
    {
        Log::info('reconciling');
    }

    #[Righteous(FacadeCall::class)]
    public function failed(\Throwable $failure): void
    {
        App::make(StockReconciler::class)->abandon($failure->getMessage());
    }
}
