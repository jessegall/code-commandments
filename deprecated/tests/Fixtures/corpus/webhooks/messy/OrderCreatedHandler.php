<?php

namespace App\Webhooks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCreatedHandler
{
    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload)
    {
        $orderId = $payload['resource_id'] ?? null;

        if ($orderId === null) {
            return;
        }

        DB::table('fulfilment_queue')->insert([
            'order_id' => $orderId,
            'created_at' => $payload['occurred_at'] ?? time(),
        ]);

        Log::info('order queued', compact('orderId'));
    }
}
