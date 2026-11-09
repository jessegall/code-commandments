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

type weighingDetector struct{ plainDetector }

func (weighingDetector) Candidates(*engine.Codebase) []Candidate { return nil }
func (weighingDetector) Decide([]Candidate) []int                { return nil }

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

func TestADetectorThatReadsBeyondOneFileRemembersAcrossTheTree(t *testing.T) {
	if !remembers(wholeTreeDetector{}) || !readsAcross(wholeTreeDetector{}) {
		t.Error("a whole-tree answer holds as long as the tree's contents do")
	}
}

func TestADetectorThatWeighsItsCandidatesRemembersAcrossTheTree(t *testing.T) {
	if !remembers(weighingDetector{}) || !readsAcross(weighingDetector{}) {
		t.Error("a weighed answer holds as long as the tree's contents do")
	}
}

func TestADetectorThatReadsOneFileDoesNotWaitOnTheWholeTree(t *testing.T) {
	if readsAcross(plainDetector{}) {
		t.Error("a one-file answer depends on that file alone")
	}
}

func TestADetectorHoldingSettingsItDoesNotFingerprintNeverRemembers(t *testing.T) {
	if remembers(settledDetector{threshold: 3}) {
		t.Error("a changed setting would be answered from the old memory")
	}
}

func TestAChangedDefinitionStartsAFreshMemory(t *testing.T) {
	before := EveryMemory().Of(identified(changingDetector{definition: "one"}))

	if EveryMemory().Of(identified(changingDetector{definition: "one"})) != before {
		t.Error("the same definition should find the same memory")
	}

	if EveryMemory().Of(identified(changingDetector{definition: "two"})) == before {
		t.Error("a changed definition must not reach the old memory")
	}
}
