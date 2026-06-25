<?php

namespace App\Inventory;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Fat controller: validation + business logic + response building all inline.
 */
class ReservationController
{
    public function store(Request $request)
    {
        // hand-rolled validation off the raw request
        $sku = $request->input('sku');
        $quantity = $request->input('quantity');

        if (! is_string($sku) || $sku == '') {
            return response()->json(['error' => 'sku required'], 422);
        }

        if (! is_array($request->input('meta', []))) {
            return response()->json(['error' => 'bad meta'], 422);
        }

        $quantity = (int) ($quantity ?? 1);

        if ($quantity < 1) {
            return response()->json(['error' => 'quantity must be >= 1'], 422);
        }

        $status = $request->input('status', 'pending');

        if ($status != 'active' && $status != 'pending') {
            return response()->json(['error' => 'bad status'], 422);
        }

        // business logic inline in the controller
        $service = app(ReservationService::class);

        $result = $service->reserve([
            'sku' => new Sku($sku),
            'quantity' => $quantity,
            'payment_type' => $request->input('payment_type', 'Stripe'),
        ]);

        Log::info('reservation attempted', ['sku' => $sku]);

        if ($result === null) {
            return response()->json(['fulfilled' => false], 200);
        }

        return response()->json($result, 200);
    }
}
