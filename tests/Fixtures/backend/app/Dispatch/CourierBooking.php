<?php

namespace Shop\Dispatch;

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

    public function bookWaybill(Waybill $waybill): string
    {
        $heavy = $waybill->isHeavy() ? ':heavy' : '';

        return $waybill->trackingCode() . ':' . $waybill->weightGrams() . $heavy;
    }
}
