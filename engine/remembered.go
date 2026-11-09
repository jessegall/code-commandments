package engine

import (
	"cmp"
	"crypto/sha256"
	"encoding/binary"
	"maps"
	"slices"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/contract"
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
	most    int
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

func (r *Remembered) AtMost(entries int) *Remembered {
	r.mu.Lock()
	defer r.mu.Unlock()

	r.most = entries

	return r
}

func (r *Remembered) Find(codebase *Codebase, find func(*Codebase) []Match) []Match {
	return r.finding(codebase, find, (*File).fingerprint)
}

func (r *Remembered) FindAcross(codebase *Codebase, find func(*Codebase) []Match) []Match {
	across := Analysis(codebase, printsAcrossKey{}, printsAcrossOf)
	if !across.whole {
		return find(codebase)
	}

	return r.finding(codebase, find, across.of)
}

func (r *Remembered) finding(codebase *Codebase, find func(*Codebase) []Match, printOf func(*File) (uint64, bool)) []Match {
	opened := codebase.opened()
	answered, asked, twins := r.recalled(opened, printOf)
	CountedJudged(len(asked))
	CountedRemembered(len(opened) - len(asked) - len(twins))

	if len(asked) == 0 {
		return answered
	}

	matches := find(codebase.Reporting(slices.Collect(maps.Keys(asked))...))
	r.remember(asked, matches)
	answered = append(answered, matches...)

	if len(twins) == 0 {
		return answered
	}

	return append(answered, r.finding(codebase.Reporting(twins...), find, printOf)...)
}

func (r *Remembered) recalled(files []*File, printOf func(*File) (uint64, bool)) ([]Match, map[string]unjudged, []string) {
	r.mu.Lock()
	defer r.mu.Unlock()

	now := r.now()
	r.sweep(now)

	var answered []Match
	var twins []string
	asked := map[string]unjudged{}
	beingAsked := map[uint64]bool{}

	for _, file := range files {
		fingerprint, printed := printOf(file)

		if printed && r.knows(fingerprint, now) && file.holdsNodes(r.found[fingerprint]) {
			r.judged[fingerprint] = now.UnixNano()
			r.changed = true

			for _, id := range r.found[fingerprint] {
				answered = append(answered, file.Match(id))
			}

			continue
		}

		if printed && beingAsked[fingerprint] {
			twins = append(twins, file.Path)

			continue
		}

		beingAsked[fingerprint] = printed
		asked[file.Path] = unjudged{file, fingerprint, printed}
	}

	return answered, asked, twins
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

	r.forgetTheOldest()
}

func (r *Remembered) forgetTheOldest() {
	if r.most <= 0 || len(r.judged) <= r.most {
		return
	}

	fingerprints := slices.SortedFunc(maps.Keys(r.judged), func(one, other uint64) int {
		return cmp.Compare(r.judged[one], r.judged[other])
	})

	for _, fingerprint := range fingerprints[:len(fingerprints)-r.most*3/4] {
		delete(r.judged, fingerprint)
		delete(r.found, fingerprint)
	}
}

func (f *File) holdsNodes(ids []int) bool {
	return !slices.ContainsFunc(ids, func(id int) bool {
		_, held := f.Node(id)

		return !held
	})
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

type printsAcrossKey struct{}

// printsAcross is what a rule that reads across files remembers a file under: the file's own print combined with the print of every file in the tree, and with its place among files of the same bytes, whose answers can differ.
type printsAcross struct {
	whole bool
	own   map[*contract.File]uint64
	mixed map[*contract.File]uint64
}

func (p printsAcross) of(file *File) (uint64, bool) {
	print, known := p.mixed[file.File]

	return print, known
}

func printsAcrossOf(c *Codebase) printsAcross {
	own, whole := c.ownPrints()
	if !whole {
		return printsAcross{}
	}

	tree := combined(slices.Sorted(maps.Values(own))...)
	mixed := make(map[*contract.File]uint64, len(c.files))
	met := map[uint64]uint64{}

	for _, file := range c.files {
		print := own[file.File]
		mixed[file.File] = combined(tree, print, met[print])
		met[print]++
	}

	return printsAcross{whole, own, mixed}
}

func (c *Codebase) ownPrints() (map[*contract.File]uint64, bool) {
	known := c.printsOfTheTreeBelow()
	own := make(map[*contract.File]uint64, len(c.files))

	for _, file := range c.files {
		if print, carried := known[file.File]; carried {
			own[file.File] = print

			continue
		}

		print, printed := file.fingerprint()
		if !printed {
			return nil, false
		}

		own[file.File] = print
	}

	return own, true
}

func (c *Codebase) printsOfTheTreeBelow() map[*contract.File]uint64 {
	if c.over == nil {
		return nil
	}

	held, built := c.over.analyses.Load(printsAcrossKey{})
	if !built {
		return nil
	}

	return held.(printsAcross).own
}

func combined(prints ...uint64) uint64 {
	written := make([]byte, 0, 8*len(prints))
	for _, print := range prints {
		written = binary.LittleEndian.AppendUint64(written, print)
	}

	sum := sha256.Sum256(written)

	return binary.LittleEndian.Uint64(sum[:8])
}
