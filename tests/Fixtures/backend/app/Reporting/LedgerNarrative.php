<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\TypeSwitch;
use JesseGall\CodeCommandments\Testing\Sinful;

interface LedgerEntry
{
    public function cents(): int;
}

final class Charge implements LedgerEntry
{
    public function __construct(private int $cents) {}

    public function cents(): int
    {
        return $this->cents;
    }
}

final class Refund implements LedgerEntry
{
    public function __construct(private int $cents) {}

    public function cents(): int
    {
        return $this->cents;
    }
}

final class Adjustment implements LedgerEntry
{
    public function __construct(private int $cents) {}

    public function cents(): int
    {
        return $this->cents;
    }
}

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
            $entry instanceof Charge => 'charged '.$entry->cents().' to the account',
            $entry instanceof Refund => 'returned '.$entry->cents().' to the customer',
            $entry instanceof Adjustment => 'adjusted '.$entry->cents().' by an operator',
            default => 'recorded',
        };
    }
}
