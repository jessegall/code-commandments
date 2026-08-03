<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Repositories\ProductRepository;

/**
 * Normalises a raw import row — string-indexing the loose array and papering over
 * every missing field with a fake empty value before persisting.
 */
final class ImportRowNormalizer
{
    private string $pendingSku = '';

    private int $pendingStock = 0;

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
     * The same import row, given its type the moment it arrives: `ImportRow::from($row)` names the
     * fields ONCE at the boundary, so nothing downstream reads `$row['sku']` off a loose array.
     *
     * @param  array<string, mixed>  $row
     */
    #[Fixed(ArrayBag::class)]
    public function ingest(array $row): void
    {
        $this->persist(ImportRow::from($row));
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

    /**
     * `?? []` filling an argument is the collection's own identity ("no items" — here the
     * empty base of a recursive merge), a real domain answer rather than a manufactured
     * scalar fake (#398). Must NOT be flagged.
     */
    #[Righteous(ManufacturedFakeFill::class)]
    public function merge(array $base, array $patch): array
    {
        return array_replace($this->childOf($base) ?? [], $patch);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function childOf(array $node): ?array
    {
        return $node === [] ? null : $node;
    }

    /**
     * PHP's serialization protocol hands `__unserialize` the raw property bag — the
     * array parameter is dictated by the LANGUAGE, so string-indexing it here (and in
     * the private helper fed only that bag) is the canonical parse point, not a sin (#340).
     */
    #[Righteous(ArrayBag::class)]
    public function __unserialize(array $data): void
    {
        $this->pendingSku = $data['sku'];
        $this->pendingStock = $this->migrateStock($data);
    }

    #[Righteous(ArrayBag::class)]
    private function migrateStock(array $data): int
    {
        return (int) ($data['stock'] ?? $data['quantity'] ?? 0);
    }
}
