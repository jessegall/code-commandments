package detectors

import (
	"reflect"
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

func FindRemembering(detector Detector, codebase *engine.Codebase) []engine.Match {
	if !remembers(detector) {
		return detector.Find(codebase)
	}

	return memoryOf(detector).Find(codebase, detector.Find)
}

func remembers(detector Detector) bool {
	if ReadsBeyondOneFile(detector) {
		return false
	}

	if _, weighs := detector.(Aggregating); weighs {
		return false
	}

	if _, fingerprinted := detector.(Fingerprinted); fingerprinted {
		return true
	}

	kind := reflect.TypeOf(detector)

	return kind.Kind() == reflect.Struct && kind.NumField() == 0
}

func memoryOf(detector Detector) *engine.Remembered {
	identity := IdentityOf(detector)
	if fingerprinted, is := detector.(Fingerprinted); is {
		identity += "\x00" + fingerprinted.Fingerprint()
	}

	return memories.Of(identity)
}

func (m *Memories) Of(identity string) *engine.Remembered {
	if memory, known := m.held.Load(identity); known {
		return memory.(*engine.Remembered)
	}

	memory, _ := m.held.LoadOrStore(identity, engine.Remembering(m.now))

	return memory.(*engine.Remembered)
}

func (m *Memories) Restore(verdicts map[string][]engine.Verdict) {
	for identity, kept := range verdicts {
		m.held.Store(identity, engine.RememberingFrom(m.now, kept))
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
