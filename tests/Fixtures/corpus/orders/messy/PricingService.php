<?php

namespace App\Orders;

use Illuminate\Support\Facades\Log;

class PricingService
{
    private $taxRate;

    public function __construct()
    {
        // grab config however
        $this->taxRate = (int) (config('orders.tax_bp') ?? 1000);
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function total($order, $currency = null)
    {
        $currency = $currency ?? 'USD';
        $subtotal = 0;

        $items = $order['line_items'] ?? [];

        if (is_array($items)) {
            foreach ($items as $item) {
                $cents = (int) ($item['unit_price']['cents'] ?? 0);
                $qty = (int) ($item['quantity'] ?? 1);
                $subtotal += $cents * $qty;
            }
        }

        // tax via a new-ed collaborator
        $calc = new TaxCalculator();
        $tax = $calc->on($subtotal);

        Log::debug('priced order', compact('subtotal', 'tax', 'currency'));

        $store = app(OrderStore::class);

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'currency' => $currency,
        ];
    }
}
