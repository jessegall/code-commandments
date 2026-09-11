<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;
use JesseGall\CodeCommandments\Sins\Backend\NearDuplicateFunction;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The price card a kiosk shows beside a product.
 */
final class PriceCard
{
    public PriceCardState $state;

    public function __construct()
    {
        $this->state = new PriceCardState;
    }

    /**
     * A named constructor that seeds its own state — the same three lines {@see StockBadge::of} writes,
     * and rightly so: `new self` binds to THIS class, so there is no procedure two classes could share,
     * only the idiom, exactly as with two `__construct`s.
     */
    #[Righteous(DuplicateFunction::class)]
    #[Righteous(NearDuplicateFunction::class)]
    public static function of(string $label, int $cents): self
    {
        $card = new self;

        $card->state->label = $label;
        $card->state->cents = $cents;

        return $card;
    }

    public function render(): string
    {
        return sprintf('%s — %.2f', $this->state->label, $this->state->cents / 100);
    }
}

final class PriceCardState
{
    public string $label = '';

    public int $cents = 0;
}
