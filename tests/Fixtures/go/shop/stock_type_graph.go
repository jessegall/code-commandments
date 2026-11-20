package shop

import "commandments.fixture/shop/typeports"

// @sin LoneImplementerInterface
type StockGraphPort interface {
	SendStockGraph() string
	ReserveStockGraph(sku string, quantity uint64) bool
}

type StockGraphWriter struct{ Inventory map[string]uint64 }

func (StockGraphWriter) SendStockGraph() string {
	return "stock"
}

func (writer StockGraphWriter) ReserveStockGraph(sku string, quantity uint64) bool {
	onHand := writer.Inventory[sku]
	if quantity > onHand {
		return false
	}
	writer.Inventory[sku] = onHand - quantity
	return true
}

type StockGraphOwner struct {
	worker *typeports.StockGraphService
	bins   map[string]bool
}

func NewStockGraphOwner(
	// @sin ConcreteCrossModuleDependency
	worker *typeports.StockGraphService,
	opened []string,
) StockGraphOwner {
	bins := make(map[string]bool, len(opened))
	for _, barcode := range opened {
		bins[barcode] = true
	}
	return StockGraphOwner{worker: worker, bins: bins}
}

func (o *StockGraphOwner) Caption() string {
	for code := range o.bins {
		return o.worker.Read() + " / " + code
	}
	return o.worker.Read()
}

type StockGraphSource struct {
	warehouse chan<- string
}

func (s StockGraphSource) Dispatch(sku string) {
	s.warehouse <- sku
}

func (s StockGraphSource) IsOpen() bool {
	return s.warehouse != nil
}

// @sin MiddleMan
type StockGraphForwarder struct {
	origin *StockGraphSource
}

func (router *StockGraphForwarder) Dispatch(sku string) {
	router.origin.Dispatch(sku)
}

func (router *StockGraphForwarder) IsOpen() bool {
	return router.origin.IsOpen()
}

// @sin HandRolledRegistry
type StockGraphDirectory struct {
	aisles map[int][]string
}

func (d *StockGraphDirectory) Replace(aisle int, bins []string) {
	d.aisles[aisle] = append([]string(nil), bins...)
}

func (d *StockGraphDirectory) Count(aisle int) int {
	if bins, counted := d.aisles[aisle]; len(bins) == 0 && !counted {
		panic("the aisle was not counted")
	}
	return len(d.aisles[aisle])
}

type StockGraphLabel struct{ Text string }
type StockGraphBin struct{ Label StockGraphLabel }
type StockGraphShelf struct{ Bin StockGraphBin }

func readStockGraph(shelves []StockGraphShelf, names map[string]int) {
	for _, shelf := range shelves {
		// @sin TrainWreck
		names[shelf.Bin.Label.Text]++
	}
}

type StockGraphDisplay struct {
	refreshes int
	// @sin UnreadCollaborator
	source *StockGraphSource
}

// @sin UnusedPrivateMember
func (d *StockGraphDisplay) preview(update func(int)) {
	d.refreshes++
	update(d.refreshes)
}

type StockGraphStage int

const (
	StockGraphOpen StockGraphStage = iota
	StockGraphClosed
)

type StockGraphBatch struct{ Stage StockGraphStage }

func (b *StockGraphBatch) Seal() {
	b.Stage = StockGraphClosed
}

func sealStockGraph(inputs <-chan *StockGraphBatch) int {
	sealed := 0
	for stock := range inputs {
		// @sin GuardBesideTransition
		if StockGraphOpen != stock.Stage {
			panic("cannot seal stock in this state")
		}
		stock.Seal()
		sealed++
	}
	return sealed
}

func captionStockGraph(state StockGraphStage, count uint64) *uint64 {
	switch state {
	case StockGraphOpen:
		return &count
	case StockGraphClosed:
		empty := uint64(0)
		return &empty
	// @sin MatchDefaultReturnsNull
	default:
		return nil
	}
}
