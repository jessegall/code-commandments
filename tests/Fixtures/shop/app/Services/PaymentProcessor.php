<?php

namespace Shop\Services;

use Illuminate\Support\Facades\Log;

final class PaymentProcessor
{
    public function charge(string $token, int $amountCents): bool
    {
        $gateway = app(PaymentGatewayRegistry::class)->get('default');

        if ($amountCents <= 0) {
            throw new \RuntimeException("Cannot charge a non-positive amount: {$amountCents}");
        }

        Log::info('charging', ['amount' => $amountCents]);

        return $gateway->send($token, $amountCents);
    }

    public function capture(int $amountCents): void
    {
        $gateway = resolve(PaymentGatewayRegistry::class)->get('default');
        $gateway->capture($amountCents);
    }
}
