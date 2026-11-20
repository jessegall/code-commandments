package shop

type StockWork interface {
	ScanCrate()
	Unpack()
	CheckQuantity()
	UpdateStock()
	NotifyBuyer()
}

// @sin DivergentTwin
func receiveStock(work StockWork) {
	work.ScanCrate()
	work.Unpack()
	work.CheckQuantity()
	work.UpdateStock()
}

// @sin DivergentTwin
func receiveOrderedStock(work StockWork) {
	work.ScanCrate()
	work.Unpack()
	work.CheckQuantity()
	work.UpdateStock()
	work.NotifyBuyer()
}
