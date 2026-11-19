<?php

namespace Shop\Payments;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\HardCodedRoutePath;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\GiftCard;

/**
 * The link to a gift card's statement in the mail sent after each top-up.
 */
final class GiftCardMailer
{
    #[Sinful(HardCodedRoutePath::class)]
    public function statementLink(GiftCard $card): string
    {
        return "/gift-cards/{$card->id}/statement";
    }

    #[Fixed(HardCodedRoutePath::class)]
    public function statementUrl(GiftCard $card): string
    {
        return route('gift-cards.statement', $card->id);
    }
}
