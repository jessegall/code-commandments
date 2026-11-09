package engine

import (
	"crypto/sha256"
	"encoding/binary"
	"maps"
	"slices"
	"sync"
	"time"
)

const RememberedFor = 12 * time.Hour

const sweptEvery = time.Hour

// Verdict is one file's remembered answer as it is kept outside memory: the fingerprint of its contents, when it was last used and the nodes found in it.
type Verdict struct {
	Fingerprint uint64
	Used        int64
	Found       []int
}

// Remembered is what one detector knows of the files it has judged, by a fingerprint of their contents and nothing else: a known file is answered with the findings it had until the fingerprint expires.
type Remembered struct {
	now     func() time.Time
	mu      sync.Mutex
	judged  map[uint64]int64
	found   map[uint64][]int
	swept   time.Time
	changed bool
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

func RememberingFrom(now func() time.Time, verdicts []Verdict) *Remembered {
	remembered := Remembering(now)

	for _, verdict := range verdicts {
		if expired(verdict.Used, now()) {
			remembered.changed = true

			continue
		}

		remembered.judged[verdict.Fingerprint] = verdict.Used

		if len(verdict.Found) > 0 {
			remembered.found[verdict.Fingerprint] = verdict.Found
		}
	}

	return remembered
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

	now := r.now()
	r.sweep(now)

	var answered []Match
	asked := map[string]unjudged{}

	for _, file := range files {
		fingerprint, printed := file.fingerprint()

		if !printed || !r.knows(fingerprint, now) {
			asked[file.Path] = unjudged{file, fingerprint, printed}

			continue
		}

		r.judged[fingerprint] = now.UnixNano()
		r.changed = true

		for _, id := range r.found[fingerprint] {
			answered = append(answered, file.Match(id))
		}
	}

	return answered, asked
}

func (r *Remembered) knows(fingerprint uint64, now time.Time) bool {
	at, known := r.judged[fingerprint]

	return known && !expired(at, now)
}

func expired(at int64, now time.Time) bool {
	return now.Sub(time.Unix(0, at)) >= RememberedFor
}

func (r *Remembered) sweep(now time.Time) {
	if now.Sub(r.swept) < sweptEvery {
		return
	}

	r.swept = now
	r.forgetExpired(now)
}

func (r *Remembered) forgetExpired(now time.Time) {
	for fingerprint, at := range r.judged {
		if !expired(at, now) {
			continue
		}

		delete(r.judged, fingerprint)
		delete(r.found, fingerprint)
		r.changed = true
	}
}

func (r *Remembered) Changed() bool {
	r.mu.Lock()
	defer r.mu.Unlock()

	r.sweep(r.now())

	return r.changed
}

func (r *Remembered) Verdicts() []Verdict {
	r.mu.Lock()
	defer r.mu.Unlock()

	r.forgetExpired(r.now())
	r.changed = false

	verdicts := make([]Verdict, 0, len(r.judged))
	for fingerprint, used := range r.judged {
		verdicts = append(verdicts, Verdict{fingerprint, used, r.found[fingerprint]})
	}

	return verdicts
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
		r.changed = true

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

		sum := sha256.Sum256(source)
		f.contents = binary.LittleEndian.Uint64(sum[:8])
		f.printed = true
	})

	return f.contents, f.printed
}
