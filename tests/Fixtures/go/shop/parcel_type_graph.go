package shop

import "commandments.fixture/shop/typeports"

// @sin LoneImplementerInterface
type ParcelGraphPort interface {
	SendParcelGraph() string
}

type ParcelGraphWriter struct{}

func (ParcelGraphWriter) SendParcelGraph() string {
	return "parcel"
}

type ParcelGraphOwner struct {
	worker *typeports.ParcelGraphService
}

func NewParcelGraphOwner(
	// @sin ConcreteCrossModuleDependency
	worker *typeports.ParcelGraphService,
) *ParcelGraphOwner {
	return &ParcelGraphOwner{worker: worker}
}

func (o *ParcelGraphOwner) Caption() string {
	return o.worker.Read() + "."
}

type ParcelGraphSource struct{ value string }

func (s ParcelGraphSource) Read() string              { return s.value }
func (s ParcelGraphSource) Record(name string) string { return s.value + ": " + name }

// @sin MiddleMan
type ParcelGraphForwarder struct {
	source ParcelGraphSource
}

func (f ParcelGraphForwarder) Read() string {
	return f.source.Read()
}

func (f ParcelGraphForwarder) Record(name string) string {
	return f.source.Record(name)
}

// @sin HandRolledRegistry
type ParcelGraphDirectory struct {
	entries map[string]string
}

func (d *ParcelGraphDirectory) Put(key string, value string) {
	d.entries[key] = value
}

func (d *ParcelGraphDirectory) Lookup(key string) string {
	if _, found := d.entries[key]; !found {
		panic("missing parcel " + key)
	}
	return d.entries[key]
}

type ParcelGraphLabel struct{ Text string }
type ParcelGraphBin struct{ Label ParcelGraphLabel }
type ParcelGraphShelf struct{ Bin ParcelGraphBin }

func readParcelGraph(shelf ParcelGraphShelf) string {
	// @sin TrainWreck
	return shelf.Bin.Label.Text
}

type ParcelGraphDisplay struct {
	// @sin UnreadCollaborator
	source *ParcelGraphSource
}

// @sin UnusedPrivateMember
func (d *ParcelGraphDisplay) preview() string {
	return "parcel"
}

type ParcelGraphStage int

const (
	ParcelGraphOpen ParcelGraphStage = iota
	ParcelGraphClosed
)

type ParcelGraphBatch struct{ Stage ParcelGraphStage }

func (b *ParcelGraphBatch) Seal() {
	b.Stage = ParcelGraphClosed
}

func sealParcelGraph(batches map[int]*ParcelGraphBatch, requested []int) {
	for _, number := range requested {
		batch, exists := batches[number]
		if !exists {
			continue
		}
		// @sin GuardBesideTransition
		if batch.Stage != ParcelGraphOpen {
			panic("already closed")
		}
		batch.Seal()
		delete(batches, number)
	}
}

func captionParcelGraph(state ParcelGraphStage) *string {
	switch state {
	case ParcelGraphOpen:
		result := "open"
		return &result
	case ParcelGraphClosed:
		result := "closed"
		return &result
	// @sin MatchDefaultReturnsNull
	default:
		return nil
	}
}
