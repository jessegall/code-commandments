<?php

namespace Shop\Catalog\Lookup;

use JesseGall\CodeCommandments\Sins\Backend\FalseForNothing;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Product positions by SKU in the warehouse index.
 */
final class SkuIndex
{
    /**
     * @param  list<string>  $skus
     */
    public function __construct(private readonly array $skus) {}

    #[Sinful(FalseForNothing::class)]
    public function positionOf(string $sku): int|false
    {
        return array_search($sku, $this->skus, true);
    }

    #[Fixed(FalseForNothing::class)]
    public function slotOf(string $sku): int
    {
        $position = array_search($sku, $this->skus, true);

        return is_int($position) ? $position : throw SkuNotIndexed::for($sku);
    }
}

/**
 * A SKU the warehouse index does not hold.
 */
final class SkuNotIndexed extends \RuntimeException
{
    public static function for(string $sku): self
    {
        return new self("{$sku} is not in the warehouse index.");
    }
}
