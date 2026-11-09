package warm

import (
	"sync"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

// Filling is how far an overlay has come: a checkout whose own files have never been read, one whose files were
// read at a moment, and one whose reading was tried and failed. An empty overlay is not a checkout with nothing
// of its own, and reading it as one is what judged a worker with thirty changed files as if it had changed none.
type Filling int

const (
	NeverFilled Filling = iota
	BeingFilled
	Filled
	FillingFailed
)

// Overlay is one checkout's own files, held over a base.
type Overlay struct {
	Checkout string

	mu      sync.Mutex
	files   map[string]*contract.File
	headers map[contract.Language]contract.Header
	marks   map[string]Known
	gone    map[string]bool
	asked   time.Time
	swept   time.Time
	filling Filling
	filled  time.Time
	why     string
	cut     string
	head    string
	base    uint64
}

func (k *Keeper) CutAt(checkout, cut string) {
	if cut == "" {
		return
	}

	k.mu.Lock()
	defer k.mu.Unlock()

	if over, held := k.overlays[checkout]; held && over.cut != "" && over.cut != cut {
		delete(k.overlays, checkout)
		k.left(checkout)
	}

	k.cutting[checkout] = cut

	if over, held := k.overlays[checkout]; held {
		over.cut = cut
	}
}

func (k *Keeper) CutOf(checkout string) string {
	k.mu.Lock()
	defer k.mu.Unlock()

	over, held := k.overlays[checkout]
	if !held {
		return ""
	}

	return over.cut
}

func (k *Keeper) Over(checkout string) *Overlay {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.sweep()

	over, held := k.overlays[checkout]
	if !held {
		over = &Overlay{
			Checkout: checkout,
			files:    map[string]*contract.File{},
			headers:  map[contract.Language]contract.Header{},
			marks:    map[string]Known{},
			gone:     map[string]bool{},
			swept:    k.now(),
			cut:      k.cutting[checkout],
		}

		k.overlays[checkout] = over
	}

	over.asked = k.now()

	return over
}

func (k *Keeper) NodesHeld() int {
	k.mu.Lock()
	defer k.mu.Unlock()

	return k.nodesHeld()
}

func (k *Keeper) nodesHeld() int {
	held := 0
	for _, base := range k.bases {
		held += base.Nodes()
	}

	for _, over := range k.overlays {
		held += over.Nodes()
	}

	return held
}

func (k *Keeper) Ceiling(nodes int) {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.ceiling = nodes
}

func (k *Keeper) PastItsCeiling() (int, int, bool) {
	k.mu.Lock()
	defer k.mu.Unlock()

	if k.ceiling <= 0 {
		return 0, 0, false
	}

	held := k.nodesHeld()

	return held, k.ceiling, held > k.ceiling
}

type Lane struct {
	Checkout string
	Cut      string
	Nodes    int
	Files    int
	Asked    time.Time
	Filling  string
}

func (k *Keeper) Lanes() []Lane {
	k.mu.Lock()
	defer k.mu.Unlock()

	var lanes []Lane
	for checkout, over := range k.overlays {
		state, _, why := over.Filling()
		said := map[Filling]string{
			NeverFilled:   "never read what the checkout differs by",
			BeingFilled:   "reading what the checkout differs by",
			Filled:        "read",
			FillingFailed: "could not be read",
		}[state]

		if why != "" {
			said += ": " + why
		}

		lanes = append(lanes, Lane{
			Checkout: checkout, Cut: over.cut, Nodes: over.Nodes(), Files: over.Count(),
			Asked: over.asked, Filling: said,
		})
	}

	return lanes
}

func (k *Keeper) DropsLane(checkout string) bool {
	k.mu.Lock()
	defer k.mu.Unlock()

	if _, held := k.overlays[checkout]; !held {
		return false
	}

	delete(k.overlays, checkout)
	k.left(checkout)

	return true
}

func (k *Keeper) DropsEveryLane() int {
	k.mu.Lock()
	defer k.mu.Unlock()

	dropped := 0
	for checkout := range k.overlays {
		delete(k.overlays, checkout)
		k.left(checkout)
		dropped++
	}

	return dropped
}

func (k *Keeper) Overlaid() int {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.sweep()

	return len(k.overlays)
}

func (k *Keeper) Forget(checkout string) {
	k.mu.Lock()
	defer k.mu.Unlock()

	delete(k.overlays, checkout)
}

func (o *Overlay) Empty() bool {
	o.mu.Lock()
	defer o.mu.Unlock()

	return len(o.files) == 0
}

func (o *Overlay) Count() int {
	o.mu.Lock()
	defer o.mu.Unlock()

	return len(o.files)
}

func (o *Overlay) Nodes() int {
	o.mu.Lock()
	defer o.mu.Unlock()

	held := 0
	for _, file := range o.files {
		held += len(file.Nodes())
	}

	return held
}

func (o *Overlay) Filling() (Filling, time.Time, string) {
	o.mu.Lock()
	defer o.mu.Unlock()

	return o.filling, o.filled, o.why
}

func (o *Overlay) BeginsFilling() bool {
	o.mu.Lock()
	defer o.mu.Unlock()

	if o.filling == BeingFilled {
		return false
	}

	o.filling = BeingFilled

	return true
}

func (o *Overlay) Fills(at time.Time, head string) {
	o.mu.Lock()
	defer o.mu.Unlock()

	o.filling, o.filled, o.why = Filled, at, ""
	o.head = head
}

func (o *Overlay) FilledAt(head string) bool {
	o.mu.Lock()
	defer o.mu.Unlock()

	if o.head == "" || head == "" {
		return true
	}

	return o.head == head
}

func (o *Overlay) LaidOver(base *Base) {
	o.mu.Lock()
	defer o.mu.Unlock()

	o.base = base.stamp
}

func (o *Overlay) IsLaidOver(base *Base) bool {
	o.mu.Lock()
	defer o.mu.Unlock()

	return o.base == base.stamp
}

func (o *Overlay) Empties() {
	o.mu.Lock()
	defer o.mu.Unlock()

	o.files = map[string]*contract.File{}
	o.headers = map[contract.Language]contract.Header{}
	o.marks = map[string]Known{}
	o.gone = map[string]bool{}
	o.filling, o.why, o.head = NeverFilled, "", ""
}

func (o *Overlay) CouldNotFill(why string) {
	o.mu.Lock()
	defer o.mu.Unlock()

	o.filling, o.why = FillingFailed, why
}

func (o *Overlay) Patch(streams []*contract.Stream, marks map[string]Known) {
	o.mu.Lock()
	defer o.mu.Unlock()

	for _, stream := range streams {
		o.headers[stream.Header.Language] = stream.Header

		for _, file := range stream.Files {
			o.files[file.Path] = file
			o.marks[file.Path] = marks[file.Path]
			delete(o.gone, file.Path)
		}
	}
}

func (o *Overlay) Removed(paths []string) {
	o.mu.Lock()
	defer o.mu.Unlock()

	o.gone = map[string]bool{}
	for _, path := range paths {
		o.gone[path] = true
	}
}

func (o *Overlay) Streams() []*contract.Stream {
	o.mu.Lock()
	defer o.mu.Unlock()

	held := map[contract.Language][]*contract.File{}
	for path, file := range o.files {
		if Mark(path) == "" {
			continue
		}

		held[file.Language] = append(held[file.Language], file)
	}

	var streams []*contract.Stream
	for language, files := range held {
		stream := contract.Stream{Header: o.headers[language], Files: files}
		streams = append(streams, &stream)
	}

	return streams
}

func (o *Overlay) Gone() []string {
	o.mu.Lock()
	defer o.mu.Unlock()

	var gone []string
	for path := range o.gone {
		gone = append(gone, path)
	}

	return gone
}

func (o *Overlay) Holds(path string) (unchanged, known bool) {
	o.mu.Lock()
	was, held := o.marks[path]
	o.mu.Unlock()

	if !held {
		return false, false
	}

	return was.Stands(path), true
}

func (o *Overlay) SweepDue(now time.Time) bool {
	o.mu.Lock()
	defer o.mu.Unlock()

	if now.Sub(o.swept) < Sweep {
		return false
	}

	o.swept = now

	return true
}
