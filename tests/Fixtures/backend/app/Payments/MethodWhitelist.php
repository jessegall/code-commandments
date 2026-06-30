<?php

namespace Shop\Payments;

use JesseGall\CodeCommandments\Sins\Backend\InArrayMirrorsEnum;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Enums\PaymentMethod;

/**
 * Whitelists payment methods by raw string membership — the set is the
 * PaymentMethod enum spelled out by hand.
 */
final class MethodWhitelist
{
    public function __construct(private readonly bool $sandbox = false) {}

    #[Sinful(InArrayMirrorsEnum::class)]
    public function allowed(string $method): bool
    {
        return in_array($method, ['card', 'ideal', 'paypal'], true);
    }

    #[Righteous(InArrayMirrorsEnum::class)]
    public function allowedClean(string $method): bool
    {
        return PaymentMethod::tryFrom($method) !== null;
    }
}
