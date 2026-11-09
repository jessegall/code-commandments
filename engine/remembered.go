package engine

import (
	"hash/maphash"
	"maps"
	"slices"
	"sync"
	"time"
)

const RememberedFor = 12 * time.Hour

const sweptEvery = time.Hour

var fingerprints = maphash.MakeSeed()

// Remembered is what one detector knows of the files it has judged, by a fingerprint of their contents and nothing else: a known file is answered with the findings it had until the fingerprint expires.
type Remembered struct {
	now    func() time.Time
	mu     sync.Mutex
	judged map[uint64]int64
	found  map[uint64][]int
	swept  time.Time
}

// unjudged is a file the detector has to be asked about, and the fingerprint its answer is remembered under.
type unjudged struct {
	file        *File
	fingerprint uint64
	printed     bool
}

func Remembering(now func() time.Time) *Remembered {
	return &Remembered{now: now, judged: map[uint64]int64{}, found: map[uint64][]int{}, swept: now()}
}

func (r *Remembered) Find(codebase *Codebase, find func(*Codebase) []Match) []Match {
	answered, asked := r.recalled(codebase.opened())
	if len(asked) == 0 {
		return answered
	}

	matches := find(codebase.Reporting(slices.Collect(maps.Keys(asked))...))
	r.remember(asked, matches)

	return append(answered, matches...)
}

func (r *Remembered) recalled(files []*File) ([]Match, map[string]unjudged) {
	r.mu.Lock()
	defer r.mu.Unlock()

	r.sweep()

	var answered []Match
	asked := map[string]unjudged{}

	for _, file := range files {
		fingerprint, printed := file.fingerprint()

		if !printed || !r.knows(fingerprint) {
			asked[file.Path] = unjudged{file, fingerprint, printed}

			continue
		}

		for _, id := range r.found[fingerprint] {
			answered = append(answered, file.Match(id))
		}
	}

	return answered, asked
}

func (r *Remembered) knows(fingerprint uint64) bool {
	at, known := r.judged[fingerprint]

	return known && r.now().Sub(time.Unix(0, at)) < RememberedFor
}

func (r *Remembered) sweep() {
	now := r.now()
	if now.Sub(r.swept) < sweptEvery {
		return
	}

	r.swept = now

	for fingerprint, at := range r.judged {
		if now.Sub(time.Unix(0, at)) >= RememberedFor {
			delete(r.judged, fingerprint)
			delete(r.found, fingerprint)
		}
	}
}

func (r *Remembered) remember(asked map[string]unjudged, matches []Match) {
	found := map[string][]int{}
	unanswerable := map[string]bool{}

	for _, match := range matches {
		one, known := asked[match.File()]
		if !known || one.file.Match(match.node.ID).node != match.node {
			unanswerable[match.File()] = true

			continue
		}

		found[match.File()] = append(found[match.File()], match.node.ID)
	}

	r.mu.Lock()
	defer r.mu.Unlock()

	at := r.now().UnixNano()

	for path, one := range asked {
		if !one.printed || unanswerable[path] {
			continue
		}

		r.judged[one.fingerprint] = at

		if ids, has := found[path]; has {
			r.found[one.fingerprint] = ids
		} else {
			delete(r.found, one.fingerprint)
		}
	}
}

func (f *File) fingerprint() (uint64, bool) {
	f.printing.Do(func() {
		CountedFile()

		source, err := f.read(f.Path)
		if err != nil || len(source) < f.File.Reaches() {
			return
		}

		f.contents = maphash.Bytes(fingerprints, source)
		f.printed = true
	})

	return f.contents, f.printed
}
