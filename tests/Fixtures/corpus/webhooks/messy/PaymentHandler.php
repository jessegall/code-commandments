<?php

namespace App\Webhooks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PaymentHandler
{
    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload)
    {
        $type = $payload['event'] ?? '';
        $orderId = $payload['resource_id'] ?? null;

        if (in_array($type, ['payment.succeeded', 'payment.refunded'])) {
            $amount = (int) ($payload['amount'] ?? 0);

            if ($type === 'payment.refunded') {
                $amount = $amount * -1;
            }

            DB::table('ledger')->insert([
                'order_id' => $orderId,
                'amount' => $amount,
            ]);

            Cache::forget('order_total_' . $orderId);
        }
    }
}
