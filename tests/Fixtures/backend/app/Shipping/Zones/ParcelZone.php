<?php

namespace Shop\Shipping\Zones;

use JesseGall\CodeCommandments\Sins\Backend\UnusedParameter;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Where a parcel goes, which sets what its carriage costs.
 */
enum ParcelZone: string
{
    case Domestic = 'domestic';
    case Abroad = 'abroad';

    #[Sinful(UnusedParameter::class)]
    public function surchargeCents(int $grams, string $currency): int
    {
        return $this === self::Abroad ? intdiv($grams, 100) * 15 : 0;
    }
}
