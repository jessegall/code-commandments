<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\KeyedLookupEnvy;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A consignment's zone already knows which regions it serves. Asking through a
 * zone table keyed by the consignment's id — then testing membership out here —
 * exiles the answer from where it lives. Move it: `$consignment->covers($region)`.
 */
final class RegionCoverage
{
    public function __construct(private readonly ZoneTable $zones) {}

    public function describe(): string
    {
        return 'region coverage via ' . ZoneTable::class;
    }

    #[Sinful(KeyedLookupEnvy::class)]
    public function covers(Consignment $consignment, string $region): bool
    {
        return $this->zones->lookup($consignment->zoneId)->includes($region);
    }
}
