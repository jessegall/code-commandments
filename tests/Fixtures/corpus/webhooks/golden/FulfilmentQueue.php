<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Accepts orders that are ready to be picked, packed and shipped.
 */
interface FulfilmentQueue
{
    public function enqueue(string $orderId): void;
}
