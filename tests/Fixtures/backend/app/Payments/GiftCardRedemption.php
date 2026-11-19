<?php

namespace Shop\Payments;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\RepeatedQueryChain;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\GiftCard;

/**
 * Pays part of an order from a gift card's balance.
 */
final class GiftCardRedemption
{
    #[Sinful(RepeatedQueryChain::class)]
    public function redeem(int $cardId, int $amountCents): int
    {
        $card = GiftCard::query()->whereKey($cardId)->lockForUpdate()->sole();

        return min($amountCents, $card->balance_cents);
    }

    #[Fixed(RepeatedQueryChain::class)]
    public function coverable(int $cardId, int $amountCents): int
    {
        return min($amountCents, GiftCard::lockedFor($cardId)->balance_cents);
    }
}
