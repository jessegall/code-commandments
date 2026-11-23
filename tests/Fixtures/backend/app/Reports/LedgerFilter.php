<?php

namespace Shop\Reports;

use JesseGall\CodeCommandments\Sins\Backend\DataClump;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * A ledger read narrowed to a till's entries between two booking days.
 */
interface LedgerFilter
{
    /**
     * @return list<string>
     */
    #[Righteous(DataClump::class)]
    public function entriesBetween(int $till, string $firstDay, string $lastDay): array;
}
