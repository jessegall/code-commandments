<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\HandRolledRegistry;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Remembers each quote it worked out, and refuses to quote a parcel while that parcel's quote is being worked out.
 */
#[Righteous(HandRolledRegistry::class)]
final class QuoteMemo
{
    private array $quotes = [];

    private array $quoting = [];

    public function quote(string $parcel, int $grams): int
    {
        if (isset($this->quoting[$parcel])) {
            throw QuoteInProgress::forParcel($parcel);
        }
        $this->quoting[$parcel] = true;

        return $this->quotes[$parcel] ??= $grams * 3;
    }
}
