<?php

namespace App\Subscriptions;

class SubscriptionService
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function start(array $data): array
    {
        $gateway = app(GatewayStore::class)->get($data['gateway'] ?? 'stripe');

        if ($gateway === null) {
            return ['status' => 'failed'];
        }

        $price = $this->priceFor($data['plan'] ?? 'free');
        $months = ($data['cycle'] ?? 'monthly') === 'annual' ? 12 : 1;

        $gateway->charge($data, $price * $months);

        return ['status' => 'active'];
    }

    private function priceFor(string $plan): int
    {
        if ($plan === 'free') {
            return 0;
        } elseif ($plan === 'pro') {
            return 2900;
        } elseif ($plan === 'enterprise') {
            return 49900;
        }

        return 0;
    }
}
