<?php

declare(strict_types=1);

namespace Billing\Gateways;

final class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function register(string $key, PaymentGateway $gateway): void
    {
        $this->gateways[$key] = $gateway;
    }

    /**
     * SMELL: a registry that returns the item OR null. A miss means the key was
     * never registered at boot — a wiring bug — not a valid "no gateway". The
     * `?? null` is a no-op, and the nullable return forces every caller to
     * re-assert the contract (see CheckoutService).
     */
    public function find(string $key): ?PaymentGateway
    {
        return $this->gateways[$key] ?? null;
    }
}
