<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\LoopInvertedGuard;

use JesseGall\CodeCommandments\Testing\Righteous;
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
    #[Sinful(LoopInvertedGuard::class)]
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

    /**
     * The first product worth reordering, reordered, and the sweep stops there. The body ends by LEAVING the
     * loop, so the condition is picking the one product it wanted rather than guarding the sweep's work:
     * inverting it into a `continue` would add a negation and say the same thing less plainly.
     *
     * @param  array<int, Product>  $products
     */
    #[Righteous(LoopInvertedGuard::class)]
    public function reorderFirst(array $products): void
    {
        foreach ($products as $product) {
            if ($product->stock < $product->reorder_point) {
                $this->raisePurchaseOrder($product);

                return;
            }
        }
    }

    private function raisePurchaseOrder(Product $product): void {}

    private function notifyBuyer(Product $product): void {}
}
