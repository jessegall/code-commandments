<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\TypeSwitch;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Prices postage for what the counter hands over: a weight typed in by hand, or a freight item it asks the kind of.
 */
final class ParcelPostage
{
    #[Sinful(TypeSwitch::class)]
    public function cents(int|Freightable $parcel): int
    {
        $cents = 0;
        if (is_int($parcel)) {
            $cents = $parcel * 2;
        } elseif ($parcel instanceof ExpressFreight) {
            $cents = $parcel->weightGrams() * 12 + 500;
        } elseif ($parcel instanceof PalletFreight) {
            $cents = $parcel->pallets() * 4_000;
        }

        return $cents;
    }
}
