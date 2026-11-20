package interfaceoverlap

type StockReader interface {
	InStock(sku string) bool
	Count(sku string) int
}

// @sin InterfaceOverlap
type WarehouseStockReader interface {
	Count(item string) int
	InStock(item string) bool
}
