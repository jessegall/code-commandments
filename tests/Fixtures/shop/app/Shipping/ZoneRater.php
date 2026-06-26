<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Detectors\Backend\ParamResolvedFromParamDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Resolves the zone from the rate card by code, then rates it. The card is only
 * carried to be unpacked; the rate is computed entirely from the zone. Pass the
 * zone.
 */
final class ZoneRater
{
    private const int REMOTE_SURCHARGE = 250;

    #[Sinful(ParamResolvedFromParamDetector::class)]
    public function rate(RateCard $card, string $zoneCode): int
    {
        $zone = $card->zoneByCode($zoneCode);

        if ($zone->isRemote()) {
            return $zone->baseCents() + self::REMOTE_SURCHARGE;
        }

        return $zone->baseCents();
    }
}

final class RateCard
{
    /** @var array<string, ShippingZone> */
    public array $zones = [];

    public function zoneByCode(string $code): ShippingZone
    {
        return $this->zones[$code];
    }
}

final class ShippingZone
{
    public function isRemote(): bool
    {
        return false;
    }

    public function baseCents(): int
    {
        return 500;
    }
}
