<?php

namespace Shop\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JesseGall\CodeCommandments\Detectors\Backend\ModelMutationAtCallSiteDetector;
use JesseGall\CodeCommandments\Detectors\Backend\RawRequestInputDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Http\Requests\CreateOrderRequest;
use Shop\Models\Order;
use Shop\Services\OrderService;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function store(CreateOrderRequest $request): Order
    {
        return $this->orders->place($request->customerId(), $request->lines());
    }

    #[Sinful(RawRequestInputDetector::class)]
    public function filter(Request $request): array
    {
        $status = $request->input('status');
        $customerId = (int) $request->input('customer_id');

        return Order::query()->where('status', $status)->where('customer_id', $customerId)->get()->all();
    }

    #[Sinful(ModelMutationAtCallSiteDetector::class)]
    public function markPaid(Request $request, Order $order): Order
    {
        $order->status = 'paid';
        $order->paid_at = now();
        $order->save();

        return $order;
    }
}
