<?php

namespace Shop\Lookups;

final class UnknownSku extends \RuntimeException
{
    public function __construct(public readonly string $sku)
    {
        parent::__construct("No price for {$sku}");
    }
}
