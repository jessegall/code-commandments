<?php

namespace App\Inventory;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Does allocation with an if/elseif ladder, news up collaborators, reaches for
 * facades and the container inside the service.
 */
class ReservationService
{
    public $store;

    public function __construct()
    {
        // new up collaborators + pull from container
        $this->store = new WarehouseStore();
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public function reserve(array $data)
    {
        $sku = $data['sku'] ?? null;
        $quantity = (int) ($data['quantity'] ?? 1);

        Log::info('reserving', $data);

        $picked = null;

        foreach ($this->store->all() as $warehouse) {
            $type = $data['payment_type'] ?? '';

            if (in_array($type, ['Stripe', 'Paypal'])) {
                $allowed = true;
            } else {
                $allowed = false;
            }

            if (! $allowed) {
                continue;
            }

            if ($warehouse->sku() && $warehouse->sku()->equals($sku) && $warehouse->canFulfil($quantity)) {
                $picked = $warehouse;
                break;
            }
        }

        if ($picked === null) {
            return null;
        }

        $picked->withdraw($quantity);

        DB::table('reservations')->insert([
            'warehouse' => $picked->code,
            'quantity' => $quantity,
        ]);

        Cache::forget('warehouses');

        $result = app(ReservationResult::class);

        return [
            'warehouseCode' => $picked->code,
            'quantity' => $quantity,
            'fulfilled' => true,
        ];
    }
}
