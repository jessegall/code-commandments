<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\MemberAfterMethod;
use JesseGall\CodeCommandments\Sins\Backend\ParamResolvedFromParam;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Resolves the zone from the rate card by code, then rates it. The card is only
 * carried to be unpacked; the rate is computed entirely from the zone. Pass the
 * zone.
 */
#[Sinful(MemberAfterMethod::class)]
final class ZoneRater
{
    #[Sinful(ParamResolvedFromParam::class)]
    public function rate(RateCard $card, string $zoneCode): int
    {
        $zone = $card->zoneByCode($zoneCode);

        if ($zone->isRemote()) {
            return $zone->baseCents() + self::REMOTE_SURCHARGE;
        }

        return $zone->baseCents();
    }

    private const int REMOTE_SURCHARGE = 250;
}

final class RateCard
{
    /**
     * @var array<string, ShippingZone>
     */
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
