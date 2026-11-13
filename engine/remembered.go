package engine

import (
	"cmp"
	"crypto/sha256"
	"encoding/binary"
	"maps"
	"path/filepath"
	"slices"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/contract"
)

const (
	sweptEvery = time.Hour

	renewedAfter = 10
)

// Verdict is one file's remembered answers, in memory and as they are kept outside it: the fingerprint of its contents, when it was last used, the rules that judged it as one bit per rule number, and the nodes each of those rules found in it.
type Verdict struct {
	Fingerprint uint64
	Used        int64
	Judged      []uint64
	Found       map[int][]int `json:",omitempty"`
}

// Memory is what a set of rules knows of the files it has judged, by a fingerprint of their contents and whether the file is a test, the one fact of its path a rule reads: one entry per file however many rules judged it, kept until the fingerprint expires.
type Memory struct {
	now     func() time.Time
	mu      sync.Mutex
	files   map[uint64]*Verdict
	swept   time.Time
	changed bool
	most    int
}

// Remembered is one rule's place in a memory: a file the rule has judged is answered with the findings the rule had in it.
type Remembered struct {
	*Memory
	rule int
}

// unjudged is a file the detector has to be asked about, and the fingerprint its answer is remembered under.
type unjudged struct {
	file        *File
	fingerprint uint64
	printed     bool
}

func NewMemory(now func() time.Time) *Memory {
	return &Memory{now: now, files: map[uint64]*Verdict{}, swept: now()}
}

func Remembering(now func() time.Time) *Remembered {
	return NewMemory(now).Rule(0)
}

func RememberingFrom(now func() time.Time, verdicts []Verdict) *Remembered {
	memory := NewMemory(now)
	memory.Restore(verdicts)

	return memory.Rule(0)
}

func (m *Memory) Rule(number int) *Remembered {
	return &Remembered{m, number}
}

func (m *Memory) Restore(verdicts []Verdict) {
	m.mu.Lock()
	defer m.mu.Unlock()

	now := m.now()
	m.files = make(map[uint64]*Verdict, len(verdicts))

	for _, verdict := range verdicts {
		if expired(verdict.Used, now) {
			m.changed = true

			continue
		}

		m.files[verdict.Fingerprint] = &verdict
	}
}

func (m *Memory) AtMost(files int) {
	m.mu.Lock()
	defer m.mu.Unlock()

	m.most = files
}

func (v *Verdict) judgedBy(rule int) bool {
	return rule/64 < len(v.Judged) && v.Judged[rule/64]&(1<<(rule%64)) != 0
}

func (v *Verdict) judged(rule int, found []int) {
	for len(v.Judged) <= rule/64 {
		v.Judged = append(v.Judged, 0)
	}

	v.Judged[rule/64] |= 1 << (rule % 64)

	if len(found) == 0 {
		delete(v.Found, rule)

		return
	}

	if v.Found == nil {
		v.Found = map[int][]int{}
	}

	v.Found[rule] = found
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

func (r *Remembered) FindForward(codebase *Codebase, find func(*Codebase) []Match) []Match {
	forward := Analysis(codebase, printsForwardKey{}, printsForwardOf)
	if !forward.whole {
		return find(codebase)
	}

	return r.finding(codebase, find, forward.of)
}

func (r *Remembered) KnownClean(codebase *Codebase) bool {
	return r.knownCleanUnder(codebase, (*File).fingerprint)
}

func (r *Remembered) KnownCleanAcross(codebase *Codebase) bool {
	across := Analysis(codebase, printsAcrossKey{}, printsAcrossOf)

	return across.whole && r.knownCleanUnder(codebase, across.of)
}

func (r *Remembered) KnownCleanForward(codebase *Codebase) bool {
	forward := Analysis(codebase, printsForwardKey{}, printsForwardOf)

	return forward.whole && r.knownCleanUnder(codebase, forward.of)
}

func (r *Remembered) knownCleanUnder(codebase *Codebase, printOf func(*File) (uint64, bool)) bool {
	r.mu.Lock()
	defer r.mu.Unlock()

	now := r.now()
	for _, file := range codebase.opened() {
		print, printed := printOf(file)
		verdict, held := r.files[print]
		if !printed || !held || expired(verdict.Used, now) || !verdict.judgedBy(r.rule) || len(verdict.Found[r.rule]) > 0 {
			return false
		}
		r.renew(verdict, now)
	}

	return true
}

func (r *Remembered) finding(codebase *Codebase, find func(*Codebase) []Match, printOf func(*File) (uint64, bool)) []Match {
	opened := codebase.opened()
	answered, asked, twins := r.recalled(printsOf(opened, printOf))
	CountedJudged(len(asked))
	CountedRemembered(len(opened) - len(asked) - len(twins))

	if len(asked) == 0 {
		return answered
	}

	codebase.FilledFor(slices.Collect(maps.Keys(asked)))
	matches := find(codebase.Reporting(slices.Collect(maps.Keys(asked))...))
	r.remember(asked, matches)
	answered = append(answered, matches...)

	if len(twins) == 0 {
		return answered
	}

	return append(answered, r.finding(codebase.Reporting(twins...), find, printOf)...)
}

func printsOf(files []*File, printOf func(*File) (uint64, bool)) []unjudged {
	prints := make([]unjudged, len(files))

	for at, file := range files {
		fingerprint, printed := printOf(file)
		prints[at] = unjudged{file, fingerprint, printed}
	}

	return prints
}

func (r *Remembered) recalled(files []unjudged) ([]Match, map[string]unjudged, []string) {
	r.mu.Lock()
	defer r.mu.Unlock()

	now := r.now()
	r.sweep(now)

	var answered []Match
	var twins []string
	asked := map[string]unjudged{}
	beingAsked := map[uint64]bool{}

	for _, one := range files {
		if found, known := r.known(one, now); known {
			for _, id := range found {
				answered = append(answered, one.file.Match(id))
			}

			continue
		}

		if one.printed && beingAsked[one.fingerprint] {
			twins = append(twins, one.file.Path)

			continue
		}

		beingAsked[one.fingerprint] = one.printed
		asked[one.file.Path] = one
	}

	return answered, asked, twins
}

func (r *Remembered) known(one unjudged, now time.Time) ([]int, bool) {
	verdict, held := r.files[one.fingerprint]
	if !one.printed || !held || expired(verdict.Used, now) || !verdict.judgedBy(r.rule) {
		return nil, false
	}

	found := verdict.Found[r.rule]
	if !one.file.holdsNodes(found) {
		return nil, false
	}

	r.renew(verdict, now)

	return found, true
}

// renew restarts the clock of an answer just used, so an answer in use is never pruned.
func (m *Memory) renew(verdict *Verdict, now time.Time) {
	if stale(verdict.Used, now) {
		verdict.Used = now.UnixNano()
		m.changed = true
	}
}

func expired(at int64, now time.Time) bool {
	return now.Sub(time.Unix(0, at)) >= cache.KeptFor()
}

func stale(at int64, now time.Time) bool {
	return now.Sub(time.Unix(0, at)) >= cache.KeptFor()/renewedAfter
}

func (m *Memory) sweep(now time.Time) {
	if now.Sub(m.swept) < sweptEvery {
		return
	}

	m.swept = now
	m.forgetExpired(now)
}

func (m *Memory) forgetExpired(now time.Time) {
	for fingerprint, verdict := range m.files {
		if !expired(verdict.Used, now) {
			continue
		}

		delete(m.files, fingerprint)
		m.changed = true
	}
}

func (m *Memory) Changed() bool {
	m.mu.Lock()
	defer m.mu.Unlock()

	m.sweep(m.now())

	return m.changed
}

func (m *Memory) Verdicts() []Verdict {
	m.mu.Lock()
	defer m.mu.Unlock()

	m.forgetExpired(m.now())
	m.changed = false

	verdicts := make([]Verdict, 0, len(m.files))
	for _, verdict := range m.files {
		verdicts = append(verdicts, Verdict{verdict.Fingerprint, verdict.Used, slices.Clone(verdict.Judged), maps.Clone(verdict.Found)})
	}

	return verdicts
}

func MergedVerdicts(older, newer []Verdict, renumbered []int, now time.Time) []Verdict {
	merged := make([]Verdict, 0, len(older)+len(newer))
	at := make(map[uint64]int, len(older)+len(newer))

	for _, verdict := range older {
		if expired(verdict.Used, now) {
			continue
		}

		at[verdict.Fingerprint] = len(merged)
		merged = append(merged, Verdict{verdict.Fingerprint, verdict.Used, slices.Clone(verdict.Judged), maps.Clone(verdict.Found)})
	}

	for _, verdict := range newer {
		place, held := at[verdict.Fingerprint]
		if !held {
			place = len(merged)
			at[verdict.Fingerprint] = place
			merged = append(merged, Verdict{Fingerprint: verdict.Fingerprint})
		}

		into := &merged[place]
		into.Used = max(into.Used, verdict.Used)

		for rule, number := range renumbered {
			if verdict.judgedBy(rule) {
				into.judged(number, verdict.Found[rule])
			}
		}
	}

	return merged
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

	now := r.now()

	for path, one := range asked {
		if !one.printed || unanswerable[path] {
			continue
		}

		r.fresh(one.fingerprint, now).judged(r.rule, found[path])
		r.changed = true
	}

	r.forgetTheOldest()
}

func (m *Memory) fresh(fingerprint uint64, now time.Time) *Verdict {
	verdict, held := m.files[fingerprint]
	if !held || expired(verdict.Used, now) {
		verdict = &Verdict{Fingerprint: fingerprint}
		m.files[fingerprint] = verdict
	}

	verdict.Used = now.UnixNano()

	return verdict
}

func (m *Memory) forgetTheOldest() {
	if m.most <= 0 || len(m.files) <= m.most {
		return
	}

	fingerprints := slices.SortedFunc(maps.Keys(m.files), func(one, other uint64) int {
		return cmp.Compare(m.files[one].Used, m.files[other].Used)
	})

	for _, fingerprint := range fingerprints[:len(fingerprints)-m.most*3/4] {
		delete(m.files, fingerprint)
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

		print, err := f.codebase.print(f.Path)
		if err != nil {
			return
		}
		if f.isTest() {
			print = sha256.Sum256(append(print[:], "\x00test"...))
		}
		f.contents = binary.LittleEndian.Uint64(print[:8])
		f.printed = true
	})

	return f.contents, f.printed
}

type printsAcrossKey struct{}

// printsAcross is what a rule that reads across files remembers a file under: the file's own print combined with the
// prints of the files its bridge read (the print of every file in the tree when the bridge names none), and with its
// place among files of the same bytes, whose answers can differ.
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

	return c.mixedUnder(combined(slices.Sorted(maps.Values(own))...), own, func(read *File) uint64 { return own[read.File] })
}

type printsForwardKey struct{}

func printsForwardOf(c *Codebase) printsAcross {
	own, whole := c.ownPrints()
	if !whole {
		return printsAcross{}
	}

	outlines := make([]uint64, 0, len(c.files))
	for _, file := range c.files {
		outlines = append(outlines, file.outlined())
	}
	slices.Sort(outlines)

	return c.mixedUnder(combined(outlines...), own, (*File).outlined)
}

func (c *Codebase) mixedUnder(tree uint64, own map[*contract.File]uint64, printOfRead func(*File) uint64) printsAcross {
	standing := standingOn{tree: tree, printOfRead: printOfRead, byPath: make(map[string]*File, len(c.files)), outside: map[string]uint64{}}
	for _, file := range c.files {
		standing.byPath[file.Path] = file
	}
	mixed := make(map[*contract.File]uint64, len(c.files))
	met := map[uint64]uint64{}

	for _, file := range c.files {
		print := own[file.File]
		mixed[file.File] = combined(standing.of(file), print, met[print])
		met[print]++
	}

	return printsAcross{true, own, mixed}
}

// standingOn is what a file's answer of a cross-file rule stands on besides its own contents: the contents of the
// files its bridge read, wherever the checkout lies, so a change re-judges only the changed file and the files that
// read it. A file whose bridge names no reads stands on the whole tree.
type standingOn struct {
	tree        uint64
	printOfRead func(*File) uint64
	byPath      map[string]*File
	outside     map[string]uint64
}

func (s standingOn) of(file *File) uint64 {
	if file.stream == nil || !file.stream.Header.Bridge.Reads {
		return s.tree
	}

	prints := make([]uint64, 0, len(file.Reads))
	for _, read := range file.Reads {
		if held, inTree := s.byPath[read]; inTree {
			prints = append(prints, s.printOfRead(held))

			continue
		}
		prints = append(prints, s.outsidePrint(file, read))
	}
	slices.Sort(prints)

	return combined(prints...)
}

// outsidePrint is the print of a file read from outside the tree, such as a lockfile; one that cannot be read prints
// as nothing, so it never stands for one that is there.
func (s standingOn) outsidePrint(file *File, path string) uint64 {
	if print, held := s.outside[path]; held {
		return print
	}
	print := uint64(0)
	if sum, err := file.codebase.print(path); err == nil {
		print = binary.LittleEndian.Uint64(sum[:8])
	}
	s.outside[path] = print

	return print
}

func (f *File) outlined() uint64 {
	f.outlining.Do(func() {
		f.outline = f.File.Outline(f.unrooted().Replace)
	})

	return f.outline
}

// unrooted takes the checkout out of a name: a TypeScript symbol is named by the absolute path of its module, so the
// same outline in another checkout prints the same.
func (f *File) unrooted() *strings.Replacer {
	roots := f.codebase.scanned
	if len(roots) == 0 {
		roots = f.stream.Header.Roots
	}
	var spelt []string
	for _, root := range roots {
		for _, spelling := range spellings(root) {
			spelt = append(spelt, strings.TrimSuffix(filepath.ToSlash(spelling), "/"))
		}
	}
	slices.SortFunc(spelt, func(a, b string) int { return len(b) - len(a) })
	pairs := make([]string, 0, 2*len(spelt))
	for _, spelling := range spelt {
		pairs = append(pairs, spelling, "@root@")
	}

	return strings.NewReplacer(pairs...)
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

	held, built := c.over.kept(printsAcrossKey{})
	if !built {
		return nil
	}

	return held.(printsAcross).own
}

func (c *Codebase) Print() (uint64, bool) {
	own, whole := c.ownPrints()
	if !whole {
		return 0, false
	}

	return PrintOfAll(slices.Collect(maps.Values(own))), true
}

func PrintOfAll(prints []uint64) uint64 {
	return combined(slices.Sorted(slices.Values(prints))...)
}

func combined(prints ...uint64) uint64 {
	written := make([]byte, 0, 8*len(prints))
	for _, print := range prints {
		written = binary.LittleEndian.AppendUint64(written, print)
	}

	sum := sha256.Sum256(written)

	return binary.LittleEndian.Uint64(sum[:8])
}
