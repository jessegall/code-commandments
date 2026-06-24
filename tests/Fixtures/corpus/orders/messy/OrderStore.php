<?php

namespace App\Orders;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Loads and keeps orders. Keyed bag, get() returns null on a miss.
 */
class OrderStore
{
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';

    /**
     * @var array<string, mixed>
     */
    private $items = [];

    public function save($order)
    {
        $this->items[$order->id] = $order;

        DB::table('orders')->insert([
            'id' => $order->id,
            'customer_id' => $order->customerId,
            'status' => $order->status,
        ]);

        Cache::forget('orders.' . $order->customerId);
        Log::info('saved order ' . $order->id);
    }

    public function get($k)
    {
        return $this->items[$k] ?? null;
    }

    /**
     * @return array<int, mixed>
     */
    public function forCustomer($customerId)
    {
        $out = [];

        foreach ($this->items as $id => $order) {
            if ($order->customerId == $customerId) {
                $out[] = $order;
            }
        }

        return $out;
    }
}
