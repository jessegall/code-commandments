<?php

namespace Shop\Inventory\Counting;

use JesseGall\CodeCommandments\Sins\Backend\ReassignedParameter;
use JesseGall\CodeCommandments\Sins\Backend\UnusedParameter;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * One stocktake of a shelf: what was counted per SKU, held against what the books expected.
 */
final class ShelfCount
{
    /**
     * @var array<string, int>
     */
    private array $counted = [];

    #[Sinful(ReassignedParameter::class)]
    public function record(string $sku, int $quantity): void
    {
        $sku = strtoupper(trim($sku));
        $this->counted[$sku] = $quantity;
    }

    #[Fixed(ReassignedParameter::class)]
    public function recordScanned(string $scanned, int $quantity): void
    {
        $sku = strtoupper(trim($scanned));
        $this->counted[$sku] = $quantity;
    }

    public function drift(string $sku, int $expected, string $countedBy): int
    {
        return $this->variance($this->counted[$sku], $expected, $countedBy);
    }

    public function shortfall(string $sku, int $expected): int
    {
        return max(0, -$this->varianceOf($this->counted[$sku], $expected));
    }

    #[Sinful(UnusedParameter::class)]
    private function variance(int $counted, int $expected, string $countedBy): int
    {
        return $counted - $expected;
    }

    #[Fixed(UnusedParameter::class)]
    private function varianceOf(int $counted, int $expected): int
    {
        return $counted - $expected;
    }
}
