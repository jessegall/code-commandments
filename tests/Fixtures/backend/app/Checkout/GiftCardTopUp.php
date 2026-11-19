<?php

namespace Shop\Checkout;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\RepeatedQueryChain;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\GiftCard;

/**
 * Adds money to a gift card bought at the till and prints what it now holds.
 */
final class GiftCardTopUp
{
    #[Sinful(RepeatedQueryChain::class)]
    public function receipt(int $id, int $cents): string
    {
        $card = GiftCard::query()->whereKey($id)->lockForUpdate()->sole();
        $after = $card->balance_cents + $cents;

        return "{$card->code}: +{$cents} = {$after}";
    }
}
