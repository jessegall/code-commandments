<?php

namespace App\Subscriptions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController
{
    public function store(Request $request)
    {
        $plan = $request->input('plan');
        $cycle = $request->input('cycle');
        $customer = $request->input('customer_id');

        if (! is_string($plan)) {
            $plan = 'free';
        }

        $service = app(SubscriptionService::class);

        $result = $service->start([
            'customer_id' => $customer,
            'plan' => $plan,
            'cycle' => $cycle ?? 'monthly',
            'gateway' => $request->input('gateway', 'stripe'),
        ]);

        Log::info('subscription started', ['plan' => $plan]);

        return response()->json(['status' => $result['status'] ?? 'unknown']);
    }
}
