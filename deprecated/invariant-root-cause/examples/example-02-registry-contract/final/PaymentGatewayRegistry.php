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
     * The contract is honest in one place: a registered key resolves, an
     * unregistered key throws. No nullable, no caller-side de-nulling.
     */
    public function get(string $key): PaymentGateway
    {
        return $this->gateways[$key]
            ?? throw UnknownPaymentGatewayException::forKey($key);
    }

    /**
     * Companion for the rare legitimate "is it registered?" question — so the
     * absence that IS valid (a feature check) has its own explicit answer,
     * separate from the invariant `get()`.
     */
    public function has(string $key): bool
    {
        return isset($this->gateways[$key]);
    }
}
