<?php

namespace Shop\Integrations;

/**
 * Righteous twin for ArrayReturnBagDetector: `config()` OVERRIDES the ancestor's
 * `config(): array` contract, so its `array` return is inherited and unchangeable —
 * it must NOT be flagged even though it returns a multi-field map.
 */
final class StripeManager extends IntegrationManager
{
    public function config(): array
    {
        return [
            'driver' => 'stripe',
            'key' => 'sk_test_x',
            'webhook' => '/stripe/webhook',
        ];
    }
}
