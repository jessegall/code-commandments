<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\DerivedArgument;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * `book()` asks for the pieces of a waybill; `bookWaybill()` asks for the waybill and reads them off it
 * where they belong — the far side of the call.
 */
final class CourierBooking
{
    public function book(string $trackingCode, int $grams, bool $heavy): string
    {
        return $trackingCode . ':' . $grams . ($heavy ? ':heavy' : '');
    }

    public function quote(string $trackingCode, int $grams): string
    {
        return $trackingCode . '@' . $grams;
    }

    #[Fixed(DerivedArgument::class)]
    public function bookWaybill(Waybill $waybill): string
    {
        $heavy = $waybill->isHeavy() ? ':heavy' : '';

        return $waybill->trackingCode() . ':' . $waybill->weightGrams() . $heavy;
    }
}
