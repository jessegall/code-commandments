<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Detectors\Backend\ConstClassEnumDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Carrier ids as integers, mirroring the rows in the old `carriers` table.
 */
#[Sinful(ConstClassEnumDetector::class)]
final class ShippingCarriers
{
    const DHL = 1;

    const UPS = 2;

    const FEDEX = 3;
}
