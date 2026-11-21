<?php

namespace App;

use App\Billing\Ledger;

final class Account
{
    private ?Ledger $ledger = null;

    public function settle(array $amounts): int
    {
        $total = 0;
        foreach ($amounts as $amount) {
            if ($amount > 0 && $amount < 100) {
                $total += $amount;
                continue;
            }
            break;
        }
        try {
            $this->ledger?->record($total);
        } catch (\RuntimeException $e) {
            throw new \LogicException('not recorded');
        }

        return $total;
    }
}
