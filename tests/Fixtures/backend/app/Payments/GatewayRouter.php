<?php

namespace Shop\Payments;

use JesseGall\CodeCommandments\Sins\Backend\StringMatchMirrorsEnum;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Enums\PaymentMethod;

/**
 * Routes to a gateway from a raw method string whose cases mirror the
 * PaymentMethod enum.
 */
final class GatewayRouter
{
    #[Sinful(StringMatchMirrorsEnum::class)]
    public function endpoint(string $method): string
    {
        return match ($method) {
            'card' => 'https://pay.test/card',
            'ideal' => 'https://pay.test/ideal',
            'paypal' => 'https://pay.test/paypal',
            default => 'https://pay.test/fallback',
        };
    }

    #[Righteous(StringMatchMirrorsEnum::class)]
    public function endpointClean(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Card => 'https://pay.test/card',
            PaymentMethod::Ideal => 'https://pay.test/ideal',
            PaymentMethod::PayPal => 'https://pay.test/paypal',
        };
    }
}
