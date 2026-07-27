<?php

namespace Shop\Ui\Wire;

use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The same shipment fields, gathered from an UNTYPED source — the opposite of the wire shapes
 * {@see ShipmentWire} writes out. There is no type here for the array to be the shape OF: a raw row
 * goes in, another loose bag comes out, and the field names are a contract nobody declares.
 */
final class ShipmentRowReader
{
    /**
     * The same three fields gathered from an UNTYPED source — a raw array parameter re-keyed into
     * another bag. No type exists here to be the shape of, so this IS the sin — twice over: the
     * returned bag, and each named field read by string key off the `array` parameter.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    #[Sinful(ArrayReturnBag::class)]
    #[Sinful(ArrayBag::class)]
    public static function fromRow(array $row): array
    {
        return [
            'carrier' => $row['carrier'],
            'status' => $row['status'],
            'tracking' => $row['tracking'],
        ];
    }
}
