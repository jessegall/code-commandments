<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Sins\Backend\ConstClassEnum;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Carrier ids as integers, mirroring the rows in the old `carriers` table.
 */
#[Sinful(ConstClassEnum::class)]
final class ShippingCarriers
{
    const DHL = 1;

    const UPS = 2;

    const FEDEX = 3;
}
