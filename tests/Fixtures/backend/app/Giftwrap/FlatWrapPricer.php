<?php

namespace Shop\Giftwrap;

/**
 * One price per parcel, and a little more with a ribbon.
 */
final class FlatWrapPricer implements WrapPricer
{
    public function cents(int $parcels, bool $ribbon): int
    {
        return $parcels * ($ribbon ? 350 : 250);
    }

    public function surchargeForOversizedParcel(int $lengthInCentimetres, int $widthInCentimetres): int
    {
        return $lengthInCentimetres + $widthInCentimetres > 150 ? 400 : 0;
    }
}
