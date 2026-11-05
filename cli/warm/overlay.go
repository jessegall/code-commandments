package warm

import (
	"sync"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

// Overlay is one checkout's own files, held over a base. A worker's worktree differs from the branch its project
// is on by a handful of files, and those files, parsed, are the whole of what makes its program its own.
//
// It is HELD rather than made for each question because an edit is never the first thing a worker did: it
// changed other files before, and deleted some, and a program carrying only the file just edited has the
// project's own copies standing in for all the rest. Every rule that reads a parent, a caller, a twin or a
// recurrence then compares the edited file with code the worker has already changed or removed.
//
// Measured in report 26: a view over a base costs 13 MB, since every file it does not replace is the base's own
// tree read by both, so a worker's overlay is the files it changed and nothing else.
type Overlay struct {
	// Checkout is the worktree the overlay belongs to. Two checkouts never share one.
	Checkout string

	mu      sync.Mutex
	files   map[string]*contract.File
	headers map[contract.Language]contract.Header
	marks     map[string]Known
	gone    map[string]bool
	asked   time.Time
	swept   time.Time
}

// Over is the overlay this checkout keeps over its base, made the first time it is asked for. It goes when
// nobody has asked about it for Idle, as a base does.
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

// Overlaid is how many overlays the keeper holds, which is what a reader of its memory asks.
func (k *Keeper) Overlaid() int {
	k.mu.Lock()
	defer k.mu.Unlock()

	k.sweep()

	return len(k.overlays)
}

// Forget drops the checkout's overlay, for a worktree that is gone or whose work has been taken in.
func (k *Keeper) Forget(checkout string) {
	k.mu.Lock()
	defer k.mu.Unlock()

	delete(k.overlays, checkout)
}

// Empty says the overlay holds nothing yet, which is what a checkout's first question answers: everything that
// checkout differs by is read then, in one ask, since a bridge's cost is per ask and not per file.
func (o *Overlay) Empty() bool {
	o.mu.Lock()
	defer o.mu.Unlock()

	return len(o.files) == 0
}

// Patch takes these streams into the overlay: the files they hold are the checkout's own from now on, each with
// what it was when it was read.
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

// Removed says the project's files at these paths are ones this checkout no longer has, so a view leaves them
// out rather than letting the project's own copies stand in their place.
func (o *Overlay) Removed(paths []string) {
	o.mu.Lock()
	defer o.mu.Unlock()

	o.gone = map[string]bool{}
	for _, path := range paths {
		o.gone[path] = true
	}
}

// Streams are the checkout's own files, one stream per language, as a view splices them in.
func (o *Overlay) Streams() []*contract.Stream {
	o.mu.Lock()
	defer o.mu.Unlock()

	held := map[contract.Language][]*contract.File{}
	for path, file := range o.files {
		// A file the checkout no longer has is not one of its own, however recently it was read.
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

// Gone are the project's files this checkout no longer has.
func (o *Overlay) Gone() []string {
	o.mu.Lock()
	defer o.mu.Unlock()

	var gone []string
	for path := range o.gone {
		gone = append(gone, path)
	}

	return gone
}

// Holds says whether the overlay holds that file, and whether it holds it as the file now is.
func (o *Overlay) Holds(path string) (unchanged, known bool) {
	o.mu.Lock()
	was, held := o.marks[path]
	o.mu.Unlock()

	if !held {
		return false, false
	}

	return was.Stands(path), true
}

// SweepDue says whether it is time to ask again what the checkout differs by, which is asked of git rather than
// of the filesystem and so is asked at most once every Sweep.
func (o *Overlay) SweepDue(now time.Time) bool {
	o.mu.Lock()
	defer o.mu.Unlock()

	if now.Sub(o.swept) < Sweep {
		return false
	}

	o.swept = now

	return true
}
