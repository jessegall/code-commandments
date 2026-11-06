package warm

import (
	"sync"
	"time"

	"github.com/jessegall/code-commandments/contract"
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
		}

		k.overlays[checkout] = over
	}

	over.asked = k.now()

	return over
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
