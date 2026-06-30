<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Sins\Backend\OptionAsNullable;

use JesseGall\CodeCommandments\Testing\Sinful;
use JesseGall\PhpTypes\Option;

/**
 * Holds a memoised lookup as `Option | null` — both an Option AND a null, two
 * absence models stacked on one field.
 */
#[Sinful(OptionAsNullable::class)]
final class PriceCache
{
    private Option | null $memoised = null;

    public function remember(Option $price): void
    {
        $this->memoised = $price;
    }

    public function isWarm(): bool
    {
        return $this->memoised !== null;
    }
}
