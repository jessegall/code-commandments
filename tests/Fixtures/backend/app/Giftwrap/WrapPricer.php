<?php

namespace Shop\Giftwrap;

use JesseGall\CodeCommandments\Sins\Backend\LoneImplementerInterface;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * What wrapping a parcel as a gift costs.
 */
#[Sinful(LoneImplementerInterface::class)]
interface WrapPricer
{
    public function cents(int $parcels, bool $ribbon): int;

    public function surchargeForOversizedParcel(int $lengthInCentimetres, int $widthInCentimetres): int;
}
