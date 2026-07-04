<?php

namespace Shop\Routing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicateRoute;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The API surface binds the webhook ingest under two POST URLs — the same
 * `WebhookIngestController::handle` reached two ways.
 */
final class ApiRouteProvider extends ServiceProvider
{
    #[Sinful(DuplicateRoute::class)]
    public function map(): void
    {
        Route::post('/api/webhooks/orders', [WebhookIngestController::class, 'handle'])->name('api.webhooks.orders');
        Route::post('/api/hooks/orders', [WebhookIngestController::class, 'handle'])->name('api.hooks.orders');
    }

    public function version(string $accept): int
    {
        if (str_contains($accept, 'v2')) {
            return 2;
        }

        return 1;
    }

    public function rateLimit(int $plan): int
    {
        return $plan * 100;
    }

    public function signature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    public function corsOrigin(string $host): string
    {
        return str_ends_with($host, '.example.com') ? $host : 'null';
    }
}
