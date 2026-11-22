package rule

import (
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
)

type plainDetector struct{}

func (plainDetector) Sin() sins.Sin                        { return nil }
func (plainDetector) Find(*engine.Codebase) []engine.Match { return nil }

type wholeTreeDetector struct{ plainDetector }

func (wholeTreeDetector) StandsOnTheTree() bool { return true }

type settledDetector struct {
	plainDetector
	threshold int
}

type changingDetector struct {
	plainDetector
	definition string
}

func (d changingDetector) Fingerprint() string { return d.definition }

type contextDetector struct {
	plainDetector
	matches []engine.Match
}

func (d contextDetector) Find(*engine.Codebase) []engine.Match { return d.matches }

func TestTheReportingFunnelLeavesLibraryLocationsOutOfFindings(t *testing.T) {
	codebase := engine.Load(&contract.Stream{Files: []*contract.File{
		{Path: "/shop/app/Checkout.php", Language: contract.PHP, Root: &contract.Node{Kind: "file"}},
		{Path: "/shop/vendor/package/Schema.php", Language: contract.PHP, Root: &contract.Node{Kind: "file"}},
	}})
	files := codebase.Files()
	local, library := files[0].Match(0), files[1].Match(0)
	detector := contextDetector{matches: []engine.Match{local, library}}
	findings := FindRemembering(detector, codebase)
	if len(findings) != 1 || findings[0].File() != local.File() {
		t.Fatalf("want only the project location, got %v", findings)
	}
	if detector.matches[1].File() != library.File() {
		t.Fatal("the detector's library context was changed by reporting")
	}
}

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

func TestOnlyAWholeTreeDetectorStandsOnTheWholeTree(t *testing.T) {
	if !standsOnTheTree(wholeTreeDetector{}) {
		t.Error("a whole-tree answer must be asked again when any file changes")
	}
	if standsOnTheTree(plainDetector{}) {
		t.Error("a rule that is not whole-tree keeps its answers under the files it read")
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
