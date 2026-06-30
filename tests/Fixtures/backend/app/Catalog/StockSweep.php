<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\LoopInvertedGuardDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Reorders low-stock products — and the sweep's logic sits a level deep inside a
 * wrapping condition. The righteous twin (`reorderGuarded`) inverts it.
 */
final class StockSweep
{
    /**
     * @param  array<int, Product>  $products
     */
    #[Sinful(LoopInvertedGuardDetector::class)]
    public function reorder(array $products): void
    {
        foreach ($products as $product) {
            if ($product->stock < $product->reorder_point) {
                $this->raisePurchaseOrder($product);
                $this->notifyBuyer($product);
            }
        }
    }

    /**
     * @param  array<int, Product>  $products
     */
    public function reorderGuarded(array $products): void
    {
        foreach ($products as $product) {
            if ($product->stock >= $product->reorder_point) {
                continue;
            }

            $this->raisePurchaseOrder($product);
        }
    }

    private function raisePurchaseOrder(Product $product): void {}

    private function notifyBuyer(Product $product): void {}
}
