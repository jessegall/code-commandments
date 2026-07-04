<?php

namespace Shop\Billing;

/**
 * Records the authorized tender, then defers to the reconciler to draw down the balance.
 */
final class LedgerWriter
{
    public function __construct(private readonly BalanceReconciler $reconciler) {}

    public function record(?GiftCard $card): int
    {
        return $this->reconciler->apply($card);
    }
}
