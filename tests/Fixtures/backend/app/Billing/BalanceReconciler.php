<?php

namespace Shop\Billing;

/**
 * Draws the settled amount from the card's remaining balance — the end of the tender's journey, five
 * files from the checkout, where it is finally read as a real card.
 */
final class BalanceReconciler
{
    public function apply(?GiftCard $card): int
    {
        return $card->remainingCents();
    }
}
