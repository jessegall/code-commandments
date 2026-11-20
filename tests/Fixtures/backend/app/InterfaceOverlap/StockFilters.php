<?php

namespace Shop\InterfaceOverlap\Stock;

use JesseGall\CodeCommandments\Sins\Shared\InterfaceOverlap;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

interface StockFilters
{
    public function warehouses(): array;
    public function region(): string|null;
}

#[Sinful(InterfaceOverlap::class)]
interface StockFiltersWithLabels
{
    public function region(): ?string;
    public function warehouses(): array;
    public function labels(int $warehouse, \DateTimeImmutable $countedOn, array $availableBrands = []): array;
}

#[Fixed(InterfaceOverlap::class)]
interface WarehouseStockFilters extends StockFilters
{
    public function labels(int $warehouse, \DateTimeImmutable $countedOn, array $availableBrands = []): array;
}
