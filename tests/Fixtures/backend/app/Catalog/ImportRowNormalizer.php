<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;

use JesseGall\CodeCommandments\Testing\Righteous;
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
    #[Sinful(ArrayBag::class)]
    #[Sinful(ManufacturedFakeFill::class)]
    public function normalize(array $row): void
    {
        $this->products->upsert(
            $row['sku'] ?? '',
            $row['name'] ?? '',
            (int) ($row['stock'] ?? 0),
        );
    }

    /**
     * Absence is decided at the source: a typed row guarantees its fields, so no
     * empty-string / zero fake is manufactured here.
     */
    #[Righteous(ManufacturedFakeFill::class)]
    public function persist(ImportRow $row): void
    {
        $this->products->upsert($row->sku, $row->name, $row->stock);
    }
}
