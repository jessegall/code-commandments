<?php

namespace Shop\Http\Controllers\DuplicateActions;

use Illuminate\Foundation\Http\FormRequest;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicateRouteAction;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The admin panel and the webhook both trigger a catalogue sync through the same StockSynchronizer::pull.
 */
class SyncRequest extends FormRequest {}

final class StockSynchronizer
{
    public function pull(SyncRequest $request): string
    {
        return 'pulled';
    }
}

final class AdminSyncController
{
    public function __construct(private readonly StockSynchronizer $sync) {}

    #[Sinful(DuplicateRouteAction::class)]
    public function pull(SyncRequest $request): string
    {
        return $this->sync->pull($request);
    }

    public function warehouseCode(string $region, int $bay): string
    {
        $prefix = substr(strtoupper($region), 0, 3);

        return $prefix . '-' . str_pad((string) $bay, 4, '0', STR_PAD_LEFT);
    }

    public function cursor(?string $since): string
    {
        return $since ?? '1970-01-01';
    }

    public function conflictStrategy(bool $force): string
    {
        return $force ? 'overwrite' : 'skip';
    }

    public function batches(int $count, int $size): int
    {
        return (int) ceil($count / max(1, $size));
    }
}

final class WebhookSyncController
{
    public function __construct(private readonly StockSynchronizer $sync) {}

    #[Sinful(DuplicateRouteAction::class)]
    public function pull(SyncRequest $request): string
    {
        return $this->sync->pull($request);
    }

    public function verifySignature(string $payload, string $signature): bool
    {
        return hash_equals(hash('sha256', $payload), $signature);
    }
}
