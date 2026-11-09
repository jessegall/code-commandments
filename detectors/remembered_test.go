package detectors

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
)

type plainDetector struct{}

func (plainDetector) Sin() sins.Sin                            { return nil }
func (plainDetector) Find(*engine.Codebase) []engine.Match     { return nil }

type wholeTreeDetector struct{ plainDetector }

func (wholeTreeDetector) WholeTree() {}

type settledDetector struct {
	plainDetector
	threshold int
}

type changingDetector struct {
	plainDetector
	definition string
}

func (d changingDetector) Fingerprint() string { return d.definition }

func TestADetectorThatReadsOneFileRemembers(t *testing.T) {
	if !remembers(plainDetector{}) {
		t.Error("a detector with no settings that reads one file should remember")
	}
}

func TestADetectorThatReadsBeyondOneFileNeverRemembers(t *testing.T) {
	if remembers(wholeTreeDetector{}) {
		t.Error("one file's contents cannot say a whole-tree answer still holds")
	}
}

func TestADetectorHoldingSettingsItDoesNotFingerprintNeverRemembers(t *testing.T) {
	if remembers(settledDetector{threshold: 3}) {
		t.Error("a changed setting would be answered from the old memory")
	}
}

func TestAChangedDefinitionStartsAFreshMemory(t *testing.T) {
	before := memoryOf(changingDetector{definition: "one"})

	if memoryOf(changingDetector{definition: "one"}) != before {
		t.Error("the same definition should find the same memory")
	}

	if memoryOf(changingDetector{definition: "two"}) == before {
		t.Error("a changed definition must not reach the old memory")
	}
}
