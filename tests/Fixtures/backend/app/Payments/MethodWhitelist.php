<?php

namespace Shop\Payments;

use JesseGall\CodeCommandments\Detectors\Backend\InArrayMirrorsEnumDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Whitelists payment methods by raw string membership — the set is the
 * PaymentMethod enum spelled out by hand.
 */
final class MethodWhitelist
{
    public function __construct(private readonly bool $sandbox = false) {}

    #[Sinful(InArrayMirrorsEnumDetector::class)]
    public function allowed(string $method): bool
    {
        return in_array($method, ['card', 'ideal', 'paypal'], true);
    }
}
