package detectors

import (
	"reflect"
	"slices"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
)

// Fingerprinted is a detector whose definition can change while the program runs: what it answers keys what it remembers, so a changed detector starts over.
type Fingerprinted interface {
	Fingerprint() string
}

// Memories is what every detector remembers, each under its identity and any fingerprint of its definition: one memory of the files judged alone and one of the files judged across the tree, where a detector's number is its bit in every file's entry.
type Memories struct {
	mu     sync.Mutex
	now    func() time.Time
	rules  []string
	held   map[string]*engine.Remembered
	own    *engine.Memory
	across *engine.Memory
}

// Kept is every detector's memory as it is saved: the detectors by number, and the files each of the two memories holds. Laid over an older Kept, a detector the older did not know takes the next number, a file both judged keeps every detector either judged it with, the newer findings winning, and an older entry left unused too long is dropped.
type Kept struct {
	Rules  []string
	Own    []engine.Verdict
	Across []engine.Verdict
}

var memories = NewMemories(time.Now)

func NewMemories(now func() time.Time) *Memories {
	across := engine.NewMemory(now)
	across.AtMost(mostRememberedAcross)

	return &Memories{now: now, held: map[string]*engine.Remembered{}, own: engine.NewMemory(now), across: across}
}

func EveryMemory() *Memories {
	return memories
}

const acrossTheTree = "\x00across"

const forwardOverTheTree = "\x00forward"

const mostRememberedAcross = 20_000

func FindRemembering(detector Detector, codebase *engine.Codebase) []engine.Match {
	if !remembers(detector) {
		codebase.Filled()

		return detector.Find(codebase)
	}

	if readsAcross(detector) && catalog.IsForward(detector) {
		return memories.Of(identified(detector)+forwardOverTheTree+acrossTheTree).FindForward(codebase, detector.Find)
	}

	if readsAcross(detector) {
		return memories.Of(identified(detector)+acrossTheTree).FindAcross(codebase, detector.Find)
	}

	return memories.Of(identified(detector)).Find(codebase, detector.Find)
}

func readsAcross(detector Detector) bool {
	_, weighs := detector.(Aggregating)

	return weighs || ReadsBeyondOneFile(detector)
}

func remembers(detector Detector) bool {
	if _, fingerprinted := detector.(Fingerprinted); fingerprinted {
		return true
	}

	kind := reflect.TypeOf(detector)

	return kind.Kind() == reflect.Struct && kind.Size() == 0
}

func identified(detector Detector) string {
	identity := IdentityOf(detector)
	if fingerprinted, is := detector.(Fingerprinted); is {
		identity += "\x00" + fingerprinted.Fingerprint()
	}

	return identity
}

func (m *Memories) Of(identity string) *engine.Remembered {
	m.mu.Lock()
	defer m.mu.Unlock()

	if memory, known := m.held[identity]; known {
		return memory
	}

	m.rules = append(m.rules, identity)

	return m.numbered(len(m.rules)-1, identity)
}

func (m *Memories) numbered(number int, identity string) *engine.Remembered {
	memory := m.own.Rule(number)
	if strings.HasSuffix(identity, acrossTheTree) {
		memory = m.across.Rule(number)
	}

	m.held[identity] = memory

	return memory
}

func (m *Memories) Restore(kept Kept) {
	m.mu.Lock()
	defer m.mu.Unlock()

	if len(m.rules) > 0 {
		return
	}

	m.rules = kept.Rules
	for number, identity := range kept.Rules {
		m.numbered(number, identity)
	}

	m.own.Restore(kept.Own)
	m.across.Restore(kept.Across)
}

func (m *Memories) Changed() bool {
	return m.own.Changed() || m.across.Changed()
}

func (m *Memories) Kept() Kept {
	m.mu.Lock()
	defer m.mu.Unlock()

	return Kept{slices.Clone(m.rules), m.own.Verdicts(), m.across.Verdicts()}
}

func (m *Memories) Over(saved Kept) Kept {
	return saved.with(m.Kept(), m.now())
}

func (k Kept) with(newer Kept, now time.Time) Kept {
	rules := slices.Clone(k.Rules)
	renumbered := make([]int, len(newer.Rules))

	for at, identity := range newer.Rules {
		number := slices.Index(rules, identity)
		if number < 0 {
			rules = append(rules, identity)
			number = len(rules) - 1
		}

		renumbered[at] = number
	}

	return Kept{rules, engine.MergedVerdicts(k.Own, newer.Own, renumbered, now), engine.MergedVerdicts(k.Across, newer.Across, renumbered, now)}
}
