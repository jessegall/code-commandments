<?php

namespace Shop\Billing;

use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A checkout that may be part-paid with a gift card. `$giftCard` is typed `?GiftCard`, but every path
 * it takes — down through the payment router, gateway, ledger and reconciler — reads it as present and
 * never guards it: the card is always there by the time settlement runs, so the nullable is a lie.
 */
#[Sinful(PhantomNullable::class)]
final class Checkout
{
    public function __construct(
        public readonly ?GiftCard $giftCard,
        public readonly int $totalCents,
    ) {}

    public function settle(PaymentRouter $router): int
    {
        return $router->route($this->giftCard);
    }
}
