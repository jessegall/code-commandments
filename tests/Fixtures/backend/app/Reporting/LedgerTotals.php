<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\ShortCircuitStatement;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Folds a ledger into per-account totals — and the skip is written as a bare `||` whose
 * result nothing reads, so the loop's real decision hides inside an expression. The
 * righteous twin (`totalsGuarded`) skips with a `continue`.
 */
final class LedgerTotals
{
    /**
     * @param  array<int, array{account: string, cents: int}>  $entries
     * @return array<string, int>
     */
    #[Sinful(ShortCircuitStatement::class)]
    public function totals(array $entries): array
    {
        $totals = [];

        foreach ($entries as $entry) {
            $entry['cents'] === 0 || $totals[$entry['account']] = ($totals[$entry['account']] ?? 0) + $entry['cents'];
        }

        return $totals;
    }

    /**
     * @param  array<int, array{account: string, cents: int}>  $entries
     * @return array<string, int>
     */
    public function totalsGuarded(array $entries): array
    {
        $totals = [];

        foreach ($entries as $entry) {
            if ($entry['cents'] === 0) {
                continue;
            }

            $totals[$entry['account']] = ($totals[$entry['account']] ?? 0) + $entry['cents'];
        }

        return $totals;
    }
}
