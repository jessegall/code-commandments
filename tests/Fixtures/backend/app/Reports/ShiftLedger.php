<?php

namespace Shop\Reports;

/**
 * The days each cashier's shift booked an entry on, keyed by the till the shift opened.
 */
final class ShiftLedger implements LedgerFilter
{
    use FiltersLedgerByDay;

    /**
     * @param  array<int, list<string>>  $entries
     */
    public function __construct(private readonly array $entries) {}

    public function opened(int $till): bool
    {
        return isset($this->entries[$till]);
    }
}
