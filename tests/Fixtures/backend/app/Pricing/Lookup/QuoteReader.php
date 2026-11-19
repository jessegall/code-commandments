<?php

namespace Shop\Pricing\Lookup;

use JesseGall\CodeCommandments\Sins\Backend\CoupledFields;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Reads stored quotes. Its two collaborators hold no state, so handing both to every quote it builds couples no
 * values a type of their own could hold.
 */
#[Righteous(CoupledFields::class)]
final class QuoteReader
{
    public function __construct(private readonly QuoteCodec $codec, private readonly QuoteRounding $rounding) {}

    public function fresh(string $sku): StoredQuote
    {
        return new StoredQuote($sku, '0', $this->codec, $this->rounding);
    }

    public function stored(string $sku, string $encoded): StoredQuote
    {
        return new StoredQuote($sku, $encoded, $this->codec, $this->rounding);
    }
}

/**
 * Turns a quote's stored text into cents.
 */
final class QuoteCodec
{
    public function cents(string $encoded): int
    {
        return (int) $encoded;
    }
}

/**
 * Rounds cents to the shop's price steps.
 */
final class QuoteRounding
{
    public function round(int $cents): int
    {
        return intdiv($cents + 4, 5) * 5;
    }
}

/**
 * One stored quote for a SKU.
 */
final readonly class StoredQuote
{
    public function __construct(
        public string $sku,
        private string $encoded,
        private QuoteCodec $codec,
        private QuoteRounding $rounding,
    ) {}

    public function cents(): int
    {
        return $this->rounding->round($this->codec->cents($this->encoded));
    }
}
