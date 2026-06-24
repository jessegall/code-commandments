<?php

declare(strict_types=1);

namespace App\Subscriptions;

/**
 * Keyed store of the payment gateways the application can dispatch to.
 */
final class GatewayRegistry
{
    /**
     * @var array<string, PaymentGateway>
     */
    private array $gateways = [];

    public function register(string $key, PaymentGateway $gateway): void
    {
        $this->gateways[$key] = $gateway;
    }

    public function has(string $key): bool
    {
        return isset($this->gateways[$key]);
    }

    public function get(string $key): PaymentGateway
    {
        return $this->gateways[$key] ?? throw GatewayNotFoundException::forKey($key);
    }
}
