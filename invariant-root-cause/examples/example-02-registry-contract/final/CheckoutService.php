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
        // The registry enforces the invariant; the caller just uses the result.
        $this->registry->get($gatewayKey)->charge($amountCents);
    }
}
