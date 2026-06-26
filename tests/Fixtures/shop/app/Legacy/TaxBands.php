<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Detectors\Backend\ConstClassEnumDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * VAT rates kept as floating-point constants instead of a typed TaxBand enum.
 */
#[Sinful(ConstClassEnumDetector::class)]
final class TaxBands
{
    /** The default rate applied to most goods. */
    const STANDARD = 0.21;

    /** Food, books, and other reduced-rate categories. */
    const REDUCED = 0.09;

    /** Exports and exempt supplies. */
    const ZERO = 0.0;
}
