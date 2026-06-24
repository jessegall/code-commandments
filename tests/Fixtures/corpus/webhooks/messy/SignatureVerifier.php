<?php

namespace App\Webhooks;

use Illuminate\Support\Facades\Log;

class SignatureVerifier
{
    /**
     * @param array<string, mixed> $data
     */
    public function verify(array $data, $signature)
    {
        $secret = config('services.webhook.secret');
        $body = $data['body'] ?? '';

        $expected = hash_hmac('sha256', $body, $secret ?? '');

        if (! hash_equals($expected, (string) ($signature ?? ''))) {
            Log::warning('signature mismatch');

            return false;
        }

        return true;
    }
}
