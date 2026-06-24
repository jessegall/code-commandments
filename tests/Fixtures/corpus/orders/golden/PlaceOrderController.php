<?php

declare(strict_types=1);

namespace App\Orders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Accepts a request to place an order and persists it as pending.
 */
final class PlaceOrderController
{
    public function __construct(
        private readonly OrderRepository $orders,
    ) {}

    public function store(PlaceOrderRequest $request): RedirectResponse
    {
        $lineItem = new LineItem(
            sku: $request->sku(),
            quantity: $request->quantity(),
            unitPrice: new Money($request->unitPriceCents(), $request->currency()),
        );

        $order = new Order(
            id: (string) Str::uuid(),
            customerId: $request->customerId(),
            status: OrderStatus::Pending,
            lineItems: collect([$lineItem]),
        );

        $this->orders->save($order);

        return redirect()->route('orders.index');
    }
}
