<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\DerivedArgument;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The label printed for a parcel: its tracking code and how heavy it reads.
 */
final class ParcelLabel
{
    private function __construct(public readonly string $code, public readonly string $weight) {}

    public static function printed(string $code, int $grams, bool $heavy): self
    {
        return new self($code, number_format($grams / 1000, 1) . ($heavy ? ' kg, lift with two' : ' kg'));
    }

    /**
     * Righteous twin: one named constructor handing its parts to its sibling, as `new self(...)` would. The class
     * builds itself; asking `printed` to take the waybill would only move the unpacking.
     */
    #[Righteous(DerivedArgument::class)]
    public static function forWaybill(Waybill $waybill): self
    {
        return self::printed($waybill->trackingCode(), $waybill->weightGrams(), $waybill->isHeavy());
    }
}
