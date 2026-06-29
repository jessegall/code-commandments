<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\MassUpdateAtCallSiteDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Publishes a product via a loose update array — the "publish" step belongs on
 * the model. The righteous version (`republish`) calls the model's own method.
 */
final class ProductPublisher
{
    #[Sinful(MassUpdateAtCallSiteDetector::class)]
    public function publish(Product $product): void
    {
        $product->update([
            'published' => true,
            'published_at' => '2026-01-01',
        ]);
    }

    public function republish(Product $product): void
    {
        $product->markPublished();
    }
}
