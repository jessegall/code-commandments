<?php

namespace Shop\Integrations;

/**
 * Keyed store of couriers, resolved by the courier binding to turn a configured name into an API.
 */
final class CourierRegistry
{
    public function preferred(): CourierApi
    {
        return $this->couriers['preferred'];
    }

    /** @var array<string, CourierApi> */
    private array $couriers = [];
}
