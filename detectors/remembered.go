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

var memories sync.Map

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

	if memory, known := memories.Load(identity); known {
		return memory.(*engine.Remembered)
	}

	memory, _ := memories.LoadOrStore(identity, engine.Remembering(time.Now))

	return memory.(*engine.Remembered)
}
