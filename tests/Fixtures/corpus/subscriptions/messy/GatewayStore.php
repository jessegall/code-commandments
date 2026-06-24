<?php

namespace App\Subscriptions;

class GatewayStore
{
    private array $gateways = [];

    public function add($key, $gateway)
    {
        $this->gateways[$key] = $gateway;
    }

    public function get($key)
    {
        return $this->gateways[$key] ?? null;
    }
}
