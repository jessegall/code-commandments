package shop

import "commandments.fixture/shop/typeports"

// @sin LoneImplementerInterface
type TillGraphPort interface {
	SendTillGraph(drawer int) (string, bool)
}

type TillGraphWriter struct{ ClosedDrawers map[int]string }

func (writer TillGraphWriter) SendTillGraph(drawer int) (string, bool) {
	code, closed := writer.ClosedDrawers[drawer]
	return code, closed
}

type TillGraphOwner struct {
	worker   *typeports.TillGraphService
	terminal chan<- string
}

func NewTillGraphOwner(
	// @sin ConcreteCrossModuleDependency
	worker *typeports.TillGraphService,
	terminal chan<- string,
) (*TillGraphOwner, bool) {
	if terminal == nil {
		return nil, false
	}
	return &TillGraphOwner{worker: worker, terminal: terminal}, true
}

func (o *TillGraphOwner) PublishCaption() {
	o.terminal <- o.worker.Read()
}

type TillGraphSource struct {
	cash     int64
	receipts map[string]int64
}

func (s *TillGraphSource) Cash() int64 { return s.cash }

func (s *TillGraphSource) Collect(receipt string, amount int64) {
	s.receipts[receipt] = amount
	s.cash += amount
}

// @sin MiddleMan
type TillGraphForwarder struct {
	source *TillGraphSource
}

func (collector *TillGraphForwarder) Cash() int64 {
	return collector.source.Cash()
}

func (collector *TillGraphForwarder) Collect(ticket string, cents int64) {
	collector.source.Collect(ticket, cents)
}

// @sin HandRolledRegistry
type TillGraphDirectory struct {
	totals map[string]int64
}

func (d *TillGraphDirectory) Update(code string, counted int64) {
	d.totals[code] = counted
}

func (d *TillGraphDirectory) Change(code string, charged int64) int64 {
	if total, counted := d.totals[code]; !counted || total < charged {
		panic("till is short of cash")
	}
	return d.totals[code] - charged
}

type TillGraphLabel struct{ Text string }
type TillGraphBin struct{ Label TillGraphLabel }
type TillGraphShelf struct{ Bin TillGraphBin }

func readTillGraph(shelf TillGraphShelf, print func(string) error) error {
	// @sin TrainWreck
	if err := print(shelf.Bin.Label.Text); err != nil {
		return err
	}
	return nil
}

type TillGraphDisplay struct {
	width int
	title string
	// @sin UnreadCollaborator
	source *TillGraphSource
}

// @sin UnusedPrivateMember
func (d *TillGraphDisplay) preview() string {
	if d.width < len(d.title) {
		return d.title[:d.width]
	}
	return d.title
}

type TillGraphStage int

const (
	TillGraphOpen TillGraphStage = iota
	TillGraphClosed
)

type TillGraphBatch struct{ Stage TillGraphStage }

func (b *TillGraphBatch) Seal() {
	b.Stage = TillGraphClosed
}

func sealTillGraph(batch *TillGraphBatch, receipt chan<- TillGraphStage) {
	// @sin GuardBesideTransition
	if batch.Stage == TillGraphClosed {
		panic("drawer already sealed")
	}
	batch.Seal()
	receipt <- batch.Stage
}

func captionTillGraph(state TillGraphStage, notes []string) *[]string {
	switch state {
	case TillGraphOpen:
		opening := append([]string{"opening received"}, notes...)
		return &opening
	case TillGraphClosed:
		final := append(append([]string(nil), notes...), "sealed")
		return &final
	// @sin MatchDefaultReturnsNull
	default:
		return nil
	}
}
