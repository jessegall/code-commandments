<?php

namespace Shop\Lookups;

use JesseGall\CodeCommandments\Testing\Sinful;

final class PriceCache
{
    public function __construct(private PriceStore $store) {}

    #[Sinful(CheckedThenRead::class)]
    public function priceOf(string $sku): int
    {
        if ($this->store->has($sku)) {
            return $this->store->get($sku);
        }

        throw new UnknownSku($sku);
    }
}
