<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantNativeCast;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N3 scenario 3 — a `Carbon::parse` at a date slot, reached in a class that maps several fields off a model.
 * Plus righteous twins: a tolerant `tryFrom` and a timezone-carrying `new DateTime` the default cast can't
 * reproduce.
 */
final class ShipmentTimes extends Data
{
    public function __construct(public readonly Carbon $shippedAt, public readonly string $carrier) {}
}

final class ShipmentTimesMapper
{
    #[Sinful(RedundantNativeCast::class)]
    public function map(object $shipment): ShipmentTimes
    {
        return ShipmentTimes::from([
            'shippedAt' => Carbon::parse($shipment->shipped_at),
            'carrier' => $shipment->carrier,
        ]);
    }
}

/**
 * RIGHTEOUS: `tryFrom` yields null on bad input (the raw scalar would throw), and `new DateTime($x, $tz)`
 * carries a timezone the default cast wouldn't reproduce — neither is redundant.
 */
final class TolerantState extends Data
{
    public function __construct(public readonly ?FulfilmentState $state, public readonly string $raw) {}
}

final class TolerantStateBuilder
{
    #[Righteous(RedundantNativeCast::class)]
    public function build(string $code): TolerantState
    {
        return TolerantState::from(['state' => FulfilmentState::tryFrom($code), 'raw' => $code]);
    }
}

final class ZonedEntry extends Data
{
    public function __construct(public readonly \DateTimeInterface $at) {}
}

final class ZonedEntryFactory
{
    #[Righteous(RedundantNativeCast::class)]
    public function record(string $raw, \DateTimeZone $zone): ZonedEntry
    {
        return ZonedEntry::from(['at' => new \DateTime($raw, $zone)]);
    }
}
