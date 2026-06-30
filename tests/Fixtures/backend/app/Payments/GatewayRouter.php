<?php

namespace Shop\Payments;

use JesseGall\CodeCommandments\Detectors\Backend\StringMatchMirrorsEnumDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Routes to a gateway from a raw method string whose cases mirror the
 * PaymentMethod enum.
 */
final class GatewayRouter
{
    #[Sinful(StringMatchMirrorsEnumDetector::class)]
    public function endpoint(string $method): string
    {
        return match ($method) {
            'card' => 'https://pay.test/card',
            'ideal' => 'https://pay.test/ideal',
            'paypal' => 'https://pay.test/paypal',
            default => 'https://pay.test/fallback',
        };
    }
}
