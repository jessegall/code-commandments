<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\RepeatedQueryChain;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\GiftCard;

/**
 * The money the shop owes on outstanding gift cards, in the ledger's own words.
 */
final class GiftCardLiability
{
    #[Sinful(RepeatedQueryChain::class)]
    public function lineFor(int $card): string
    {
        $held = GiftCard::query()->whereKey($card)->lockForUpdate()->sole();

        return sprintf('%s owes %.2f', $held->code, $held->balance_cents / 100);
    }

    #[Righteous(RepeatedQueryChain::class)]
    public function total(): int
    {
        return GiftCard::query()->where('balance_cents', '>', 0)->sum('balance_cents');
    }

    #[Righteous(RepeatedQueryChain::class)]
    public function unused(): int
    {
        return GiftCard::query()->where('balance_cents', '>', 9999)->count();
    }
}
