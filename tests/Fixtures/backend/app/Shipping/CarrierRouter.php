<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\EnumCaseOrChain;

use Shop\Enums\ShippingMethod;
use JesseGall\CodeCommandments\Testing\Sinful;

final class CarrierRouter
{
    private string $depot = 'central';

    public function route(ShippingMethod $method): string
    {
        return $this->needsCourier($method) ? 'courier-api' : 'in-store';
    }

    #[Sinful(EnumCaseOrChain::class)]
    private function needsCourier(ShippingMethod $method): bool
    {
        return $method === ShippingMethod::Standard || $method === ShippingMethod::Express;
    }
}
