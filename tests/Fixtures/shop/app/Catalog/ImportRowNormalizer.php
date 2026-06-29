<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\ArrayBagDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ManufacturedFakeFillDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Repositories\ProductRepository;

/**
 * Normalises a raw import row — string-indexing the loose array and papering over
 * every missing field with a fake empty value before persisting.
 */
final class ImportRowNormalizer
{
    public function __construct(private readonly ProductRepository $products) {}

    /**
     * @param  array<string, mixed>  $row
     */
    #[Sinful(ArrayBagDetector::class)]
    #[Sinful(ManufacturedFakeFillDetector::class)]
    public function normalize(array $row): void
    {
        $this->products->upsert(
            $row['sku'] ?? '',
            $row['name'] ?? '',
            (int) ($row['stock'] ?? 0),
        );
    }
}
