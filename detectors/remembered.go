package detectors

import (
	"reflect"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/engine"
)

// Fingerprinted is a detector whose definition can change while the program runs: what it answers keys what it remembers, so a changed detector starts over.
type Fingerprinted interface {
	Fingerprint() string
}

// Memories is what every detector remembers, each under its identity and any fingerprint of its definition.
type Memories struct {
	now  func() time.Time
	held sync.Map
}

var memories = NewMemories(time.Now)

func NewMemories(now func() time.Time) *Memories {
	return &Memories{now: now}
}

func EveryMemory() *Memories {
	return memories
}

const acrossTheTree = "\x00across"

const mostRememberedAcross = 20_000

func FindRemembering(detector Detector, codebase *engine.Codebase) []engine.Match {
	if !remembers(detector) {
		return detector.Find(codebase)
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

func boundedFor(identity string, memory *engine.Remembered) *engine.Remembered {
	if strings.HasSuffix(identity, acrossTheTree) {
		return memory.AtMost(mostRememberedAcross)
	}

	return memory
}

func (m *Memories) Of(identity string) *engine.Remembered {
	if memory, known := m.held.Load(identity); known {
		return memory.(*engine.Remembered)
	}

	memory, _ := m.held.LoadOrStore(identity, boundedFor(identity, engine.Remembering(m.now)))

	return memory.(*engine.Remembered)
}

func (m *Memories) Restore(verdicts map[string][]engine.Verdict) {
	for identity, kept := range verdicts {
		m.held.Store(identity, boundedFor(identity, engine.RememberingFrom(m.now, kept)))
	}
}

func (m *Memories) Changed() bool {
	changed := false

	m.held.Range(func(_, memory any) bool {
		changed = memory.(*engine.Remembered).Changed() || changed

		return true
	})

	return changed
}

func (m *Memories) Verdicts() map[string][]engine.Verdict {
	verdicts := map[string][]engine.Verdict{}

	m.held.Range(func(identity, memory any) bool {
		verdicts[identity.(string)] = memory.(*engine.Remembered).Verdicts()

		return true
	})

	return verdicts
}
