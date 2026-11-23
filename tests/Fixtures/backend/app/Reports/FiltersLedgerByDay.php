<?php

namespace Shop\Reports;

use JesseGall\CodeCommandments\Sins\Backend\DataClump;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Answers the ledger filter for a class that holds its booked entries by day.
 */
trait FiltersLedgerByDay
{
    /**
     * @return list<string>
     */
    #[Righteous(DataClump::class)]
    public function entriesBetween(int $till, string $firstDay, string $lastDay): array
    {
        return array_values(array_filter(
            $this->entries[$till] ?? [],
            static fn (string $day) => $day >= $firstDay && $day <= $lastDay,
        ));
    }
}
