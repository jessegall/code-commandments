<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Detectors\Backend\NullableCallbackDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Computes a line total, optionally piping the result through a rounding hook.
 * The hook is coalesced to an inline default at the call site instead of being
 * the parameter's Null Object default.
 */
final class PriceCalculator
{
    public function __construct(private readonly int $vatPercent = 21) {}

    #[Sinful(NullableCallbackDetector::class)]
    public function lineTotal(int $cents, int $quantity, callable | null $round = null): int
    {
        $gross = $cents * $quantity;
        $withVat = $gross + (int) ($gross * $this->vatPercent / 100);

        return ($round ?? static fn (int $value): int => $value)($withVat);
    }
}
