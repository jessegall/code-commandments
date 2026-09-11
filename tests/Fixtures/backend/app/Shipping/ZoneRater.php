<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\MemberAfterMethod;
use JesseGall\CodeCommandments\Sins\Backend\NullableRegistryLookup;
use JesseGall\CodeCommandments\Sins\Backend\ParamResolvedFromParam;
use JesseGall\CodeCommandments\Testing\Righteous;
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

    /**
     * The RESOLVER itself — the lookup, the named refusal, the zone handed back and nothing more. This
     * is where the rule says the resolution and its not-found failure live; a caller holding the card
     * passes the zone downstream BECAUSE this exists.
     */
    #[Righteous(ParamResolvedFromParam::class)]
    public function zoneOf(RateCard $card, string $zoneCode): ShippingZone
    {
        $zone = $card->zoneIfKnown($zoneCode);

        if ($zone === null) {
            throw UnknownZone::for($zoneCode);
        }

        return $zone;
    }

    private const int REMOTE_SURCHARGE = 250;
}

final class UnknownZone extends \RuntimeException
{
    public static function for(string $code): self
    {
        return new self("no zone is coded {$code}");
    }
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

    /**
     * The nullable lookup {@see ZoneRater::zoneOf} resolves — its own sin, kept only so the resolver
     * above has something to guard.
     */
    #[Sinful(NullableRegistryLookup::class)]
    public function zoneIfKnown(string $code): ?ShippingZone
    {
        return $this->zones[$code] ?? null;
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
