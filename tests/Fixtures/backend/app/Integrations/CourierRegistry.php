<?php

namespace Shop\Integrations;

use JesseGall\CodeCommandments\Sins\Backend\MemberAfterMethod;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Keyed store of couriers, resolved by the courier binding to turn a configured name into an API.
 */
#[Sinful(MemberAfterMethod::class)]
final class CourierRegistry
{
    public function preferred(): CourierApi
    {
        return $this->couriers['preferred'];
    }

    /** @var array<string, CourierApi> */
    private array $couriers = [];
}
