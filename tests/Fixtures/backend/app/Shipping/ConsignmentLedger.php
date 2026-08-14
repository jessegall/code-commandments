<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\KeyedLookupEnvy;

use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Righteous twin for KeyedLookupEnvy: a fluent query-builder chain is not a keyed fetch. Every link
 * is structurally "a call on the result of a call", but the chain hands back the SAME builder each
 * hop and nothing is READ off the consignment — `$consignment->zoneId` is a WHERE binding, not a key
 * into a store of facts about it. Envy needs the fetch made ON the collaborator (#479).
 */
final class ConsignmentLedger
{
    public function __construct(private readonly QueryConnection $database) {}

    #[Righteous(KeyedLookupEnvy::class)]
    public function isDispatched(Consignment $consignment, string $region): bool
    {
        return $this->database->table('dispatches as d')
            ->join('zones as z', 'z.id', '=', 'd.zone_id')
            ->where('z.zone_id', $consignment->zoneId)
            ->where('d.region', $region)
            ->whereNotNull('d.dispatched_at')
            ->exists();
    }
}
