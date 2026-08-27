<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\GenericException;
use JesseGall\CodeCommandments\Sins\Backend\MessageAtThrow;
use JesseGall\CodeCommandments\Testing\Fixed;
use RuntimeException;

/**
 * Where the message went. The failure is named once, so every throw site says WHAT went wrong
 * instead of spelling out a sentence the next site has to spell differently.
 */
#[Fixed(GenericException::class)]
#[Fixed(MessageAtThrow::class)]
final class CarrierMissing extends RuntimeException
{
    public static function for(int|string $shipment): self
    {
        return new self("Shipment {$shipment} has no carrier.");
    }
}
