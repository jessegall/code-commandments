<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\TypeSwitch;
use JesseGall\CodeCommandments\Testing\Sinful;

interface LedgerEntry {}

final class Charge implements LedgerEntry {}

final class Refund implements LedgerEntry {}

final class Adjustment implements LedgerEntry {}

/**
 * Names a ledger entry for a statement. A `match (true)` over types is the same switch as a
 * ladder — the arms happen to line up neatly, which only makes the missing method easier to miss.
 */
final class LedgerNarrative
{
    #[Sinful(TypeSwitch::class)]
    public function describe(LedgerEntry $entry): string
    {
        return match (true) {
            $entry instanceof Charge => 'charged to the account',
            $entry instanceof Refund => 'returned to the customer',
            $entry instanceof Adjustment => 'corrected by an operator',
            default => 'recorded',
        };
    }
}
