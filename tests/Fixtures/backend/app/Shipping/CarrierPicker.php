<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Detectors\Backend\StringMatchMirrorsEnumDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Switches over a raw method string whose cases mirror the ShippingMethod enum.
 */
final class CarrierPicker
{
    #[Sinful(StringMatchMirrorsEnumDetector::class)]
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
