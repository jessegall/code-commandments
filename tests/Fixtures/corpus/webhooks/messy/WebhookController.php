<?php

namespace App\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController
{
    public function store(Request $request)
    {
        $event = $request->input('event');
        $resourceId = $request->input('resource_id');
        $occurredAt = $request->input('occurred_at');
        $signature = $request->header('X-Signature');

        if (! is_string($event)) {
            $event = 'unknown';
        }

        $data = [
            'event' => $event,
            'resource_id' => $resourceId,
            'occurred_at' => (int) ($occurredAt ?? time()),
            'amount' => $request->input('amount', 0),
            'body' => $request->getContent(),
            'signature' => $signature,
        ];

        extract($data);

        if (! in_array($event, ['order.created', 'payment.succeeded', 'payment.refunded'])) {
            return response()->json(['status' => 'unknown_event'], 422);
        }

        $processor = app(WebhookProcessor::class);
        $result = $processor->process($data);

        Log::info('webhook handled', ['event' => $event]);

        return response()->json(['status' => $result['status'] ?? 'unknown']);
    }
}
