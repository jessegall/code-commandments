<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Prints the greeting at the top of a till's screen, naming the cashier who signed in.
 */
#[Sinful(PhantomNullable::class)]
final class TillGreeter
{
    private ?CashierOnDuty $cashier = null;

    public function signIn(CashierOnDuty $cashier): void
    {
        $this->cashier = $cashier;
    }

    public function greeting(): string
    {
        return 'Served by ' . $this->cashier->name;
    }
}
