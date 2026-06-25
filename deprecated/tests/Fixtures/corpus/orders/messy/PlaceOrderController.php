<?php

namespace App\Orders;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlaceOrderController
{
    public function store(Request $request)
    {
        // validation by hand
        $customerId = $request->input('customer_id');
        $sku = $request->input('sku');
        $quantity = (int) ($request->input('quantity') ?? 1);
        $unitPriceCents = (int) ($request->input('unit_price_cents') ?? 0);
        $currency = $request->input('currency') ?? 'USD';

        if (! $customerId || ! $sku) {
            return response()->json(['error' => 'missing fields'], 422);
        }

        $items = $request->input('items');
        if (is_array($items)) {
            $first = $items[0] ?? [];
            $sku = $first['sku'] ?? $sku;
        }

        $status = $request->input('status') ?? 'pending';
        if ($status != 'pending' && $status != 'paid') {
            $status = 'pending';
        }

        $lineItem = new LineItem([
            'sku' => $sku,
            'quantity' => $quantity,
            'unit_price' => ['cents' => $unitPriceCents, 'currency' => $currency],
        ]);

        $order = new Order([
            'id' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'status' => $status,
            'line_items' => [$lineItem],
        ]);

        $store = app(OrderStore::class);
        $store->save($order);

        $pricing = new PricingService();
        $total = $pricing->total([
            'line_items' => [
                ['unit_price' => ['cents' => $unitPriceCents, 'currency' => $currency], 'quantity' => $quantity],
            ],
        ], $currency);

        Log::info('placed order', compact('customerId', 'sku', 'total'));

        return response()->json([
            'id' => $order->id,
            'status' => $order->status,
            'total' => $total['total'] ?? 0,
        ]);
    }
}
