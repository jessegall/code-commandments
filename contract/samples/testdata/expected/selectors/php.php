<?php

namespace App;

use App\Billing\Ledger;

final class Account
{
    private ?Ledger $ledger = null;

    public function settle(array $amounts): int
    {
        $total = 0;
        // @contract kind/loop
        // @contract fact/loop/form = "for-each"
        foreach ($amounts as $amount) { // @label each loop
            // @contract role/if/condition -> both
            if ($amount > 0 && $amount < 100) { // @label both logical
                $total += $amount; // @contract kind/assignment
                continue; // @contract link/continue/destination -> each
            }
            break; // @contract link/break/destination -> each
        }
        try {
            $this->ledger?->record($total);
        } catch (\RuntimeException $e) {
            throw new \LogicException('not recorded');
        }

        return $total;
    }
}
