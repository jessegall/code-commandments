<?php

namespace App\Notifications;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// fat controller: validation + business logic + response, all inline
class NotificationController
{
    public function send(Request $request)
    {
        // raw $request->input with is_array/cast/?? guards, no rules()
        $type = $request->input('type') ?? 'email';
        $recipient = $request->input('recipient');
        $meta = $request->input('meta');

        if (! is_array($meta)) {
            $meta = [];
        }

        if ($recipient == null || $recipient == '') {
            return response()->json(['error' => 'recipient required'], 422);
        }

        $retries = (int) ($request->input('retries') ?? 1);

        // magic-string closed-set check
        $status = $request->input('status') ?? 'pending';
        if (! in_array($status, ['active', 'pending'])) {
            return response()->json(['error' => 'bad status'], 422);
        }

        // type-name classification via in_array on provider strings
        $provider = $request->input('provider') ?? 'Stripe';
        if (in_array($provider, ['Stripe', 'Paypal'])) {
            Log::info('billable provider ' . $provider);
        }

        $data = [
            'recipient' => $recipient,
            'subject' => $request->input('subject') ?? '',
            'body' => $request->input('body') ?? '',
            'type' => $type,
            'retries' => $retries,
            'meta' => $meta,
        ];

        // business logic in the controller
        $dispatcher = app(NotificationDispatcher::class);
        $result = $dispatcher->dispatch($data);

        DB::table('audit')->insert(['type' => $type, 'status' => $status]);

        if ($result['ok'] ?? true) {
            return response()->json(['sent' => true, 'data' => $data]);
        }

        return response()->json(['sent' => false], 500);
    }
}
