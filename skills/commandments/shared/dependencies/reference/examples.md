# Dependencies: keep imports going in one direction — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### interface-overlap — in PHP

An interface repeats the signatures of a contract it can reuse.

```php
----------[ Bad ]----------

interface StockFiltersWithLabels
{
    public function region(): ?string;
    public function warehouses(): array;
    public function labels(int $warehouse, \DateTimeImmutable $countedOn, array $availableBrands = []): array;
}

----------[ Good ]----------

interface WarehouseStockFilters extends StockFilters
{
    public function labels(int $warehouse, \DateTimeImmutable $countedOn, array $availableBrands = []): array;
}
```

### interface-overlap — in Go

An interface repeats the signatures of a contract it can reuse.

```go
----------[ Bad ]----------

type LabeledInvoiceTotals interface {
	Taxes(number int) map[string]int
	Label(number int) string
	Net(number int) int
}

----------[ Good ]----------

type NamedInvoiceTotals interface {
	InvoiceTotals
	Label(invoice int) string
}
```
