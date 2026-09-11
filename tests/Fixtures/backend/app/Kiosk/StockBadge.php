<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;
use JesseGall\CodeCommandments\Sins\Backend\NearDuplicateFunction;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The stock badge a kiosk shows on a product tile.
 */
final class StockBadge
{
    public StockBadgeState $state;

    public function __construct()
    {
        $this->state = new StockBadgeState;
    }

    /**
     * The twin of {@see PriceCard::of} — a seeder of its OWN class, not a copy of anything.
     */
    #[Righteous(DuplicateFunction::class)]
    #[Righteous(NearDuplicateFunction::class)]
    public static function of(string $label, int $cents): self
    {
        $badge = new self;

        $badge->state->label = $label;
        $badge->state->cents = $cents;

        return $badge;
    }

    public function render(): string
    {
        return $this->state->cents > 0 ? $this->state->label . ' in stock' : 'sold out';
    }
}

final class StockBadgeState
{
    public string $label = '';

    public int $cents = 0;
}
