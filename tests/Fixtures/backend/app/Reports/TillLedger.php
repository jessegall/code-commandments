<?php

namespace Shop\Reports;

/**
 * The days each till booked an entry on.
 */
final class TillLedger implements LedgerFilter
{
    use FiltersLedgerByDay;

    /**
     * @param  array<int, list<string>>  $entries
     */
    public function __construct(private readonly array $entries) {}
}
