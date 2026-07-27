<?php

namespace Shop\Ui\Wire;

use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;

use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Enums\OrderStatus;

/**
 * The wire shape of a shipment — the SERIALIZATION boundary. Each array here is one already-typed
 * object written out in the format its receiver mandates, so the keys are that receiver's contract
 * (the JSON the client parses, the columns the row carries), not an undocumented one. Inserting a
 * second type here would only be flattened into the identical array at the same call site, so these
 * are righteous twins of the loose bags {@see \Shop\Reporting\SalesReport} hands back.
 */
final class ShipmentWire
{
    public function __construct(
        public readonly string $carrier,
        public readonly OrderStatus $status,
        public readonly TrackingWire $tracking,
        public readonly ?array $legs,
    ) {}

    /**
     * The object's OWN fields written out — dressed on the way: a field asked to serialize itself,
     * a null guard, a map over a field. The type is right here (this class), so this is its wire
     * shape, not an unborn type.
     *
     * @return array<string, mixed>
     */
    #[Righteous(ArrayReturnBag::class)]
    public function toWire(): array
    {
        return [
            'carrier' => $this->carrier,
            'tracking' => $this->tracking->toWire(),
            'legs' => $this->legs === null
                ? null
                : array_map(static fn (self $leg): array => $leg->toWire(), $this->legs),
        ];
    }

    /**
     * A persistence row projected from ONE value-typed parameter — the columns the receiver
     * mandates, read off a type that already exists. (A backed enum's `->value` is that field's own
     * scalar, not a reach into another object.)
     *
     * @return array<string, mixed>
     */
    #[Righteous(ArrayReturnBag::class)]
    public static function rowFor(ShipmentWire $shipment): array
    {
        return [
            'carrier' => $shipment->carrier,
            'status' => $shipment->status->value,
            'tracking' => $shipment->tracking->toWire(),
        ];
    }
}
