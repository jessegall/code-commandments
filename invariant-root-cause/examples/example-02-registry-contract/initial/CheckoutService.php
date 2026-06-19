<?php

declare(strict_types=1);

namespace Billing;

use Billing\Gateways\PaymentGatewayRegistry;

final class CheckoutService
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
    ) {}

    public function charge(string $gatewayKey, int $amountCents): void
    {
        // Every caller immediately de-nulls the registry result — proof the
        // nullable contract is unearned. The contract is re-asserted here (and
        // in every other caller) instead of living once in the registry.
        $gateway = $this->registry->find($gatewayKey)
            ?? throw new \RuntimeException("Unknown gateway: {$gatewayKey}");

        $gateway->charge($amountCents);
    }
}
