<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\StringMatchMirrorsEnum;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Switches over a raw method string whose cases mirror the ShippingMethod enum.
 */
final class CarrierPicker
{
    #[Sinful(StringMatchMirrorsEnum::class)]
    public function carrier(string $method): string
    {
        switch ($method) {
            case 'standard':
                return 'PostNL';
            case 'express':
                return 'DHL';
            case 'pickup':
                return 'Store';
            default:
                return 'Unknown';
        }
    }
}
