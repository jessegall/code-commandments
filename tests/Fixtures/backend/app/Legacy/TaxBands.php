<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Sins\Backend\ConstClassEnum;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * VAT rates kept as floating-point constants instead of a typed TaxBand enum.
 */
#[Sinful(ConstClassEnum::class)]
final class TaxBands
{
    /** The default rate applied to most goods. */
    const STANDARD = 0.21;

    /** Food, books, and other reduced-rate categories. */
    const REDUCED = 0.09;

    /** Exports and exempt supplies. */
    const ZERO = 0.0;
}

/**
 * The sealed set as a native backed enum — the cases now have a home for behaviour
 * and the type proves only a real band can flow through. Rates as basis points.
 */
#[Righteous(ConstClassEnum::class)]
enum TaxBand: int
{
    case Standard = 2100;
    case Reduced = 900;
    case Zero = 0;
}
