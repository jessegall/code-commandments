package golang_test

import (
	"bytes"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge/gotree"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	godetectors "github.com/jessegall/code-commandments/detectors/go"
	"github.com/jessegall/code-commandments/engine"
	_ "github.com/jessegall/code-commandments/engine/golang"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/fixture/fixturetest"
	_ "github.com/jessegall/code-commandments/registry"
)

// calibrating are the Go detectors still being calibrated, which no catalog lists. They are proven here by name
// until each has been read against real Go code and published, so a rule is never half-built AND unproven.
var calibrating = []detectors.Detector{
	godetectors.DeepNestingDetector{},
	godetectors.RedundantElseDetector{},
	godetectors.LoopWrappedInIfDetector{},
}

// shop is tests/Fixtures/go as the engine reads it, through the real bridge. The Go bridge runs in this very
// process, so the fixture is read live rather than from a frozen stream.
func shop(t *testing.T) *engine.Codebase {
	t.Helper()
	root, err := filepath.Abs("../../tests/Fixtures/go")
	if err != nil {
		t.Fatal(err)
	}

	var written bytes.Buffer
	if err := gotree.Write(&written, []string{root}, nil); err != nil {
		t.Fatal(err)
	}
	stream, err := contract.ReadAll(bytes.NewReader(written.Bytes()))
	if err != nil {
		t.Fatalf("the fixture's stream breaks the contract: %v", err)
	}

	return engine.Load(stream)
}

// TestEveryGoDetectorFlagsExactlyWhatTheFixtureMarks holds every Go rule to the fixture's own markers: a sin it
// misses is a hole, code it flags that nothing marks is a false positive, and a rule that flags its own fix or
// its righteous twin has learned the wrong shape. The markers ARE the spec.
func TestEveryGoDetectorFlagsExactlyWhatTheFixtureMarks(t *testing.T) {
	known := append(slices.Clone(detectors.Of(catalog.Go)), calibrating...)

	fixturetest.Prove(t, fixture.Fixture{Codebase: shop(t), Detectors: known, Known: known})
}

// TestEverySinTheGoFixtureMarksHasADetector holds the fixture to the rules: a marker naming a sin nothing finds
// is a spec with nothing behind it.
func TestEverySinTheGoFixtureMarksHasADetector(t *testing.T) {
	codebase := shop(t)
	known := append(slices.Clone(detectors.Of(catalog.Go)), calibrating...)

	var unknown []string
	for _, marker := range fixture.Markers(codebase) {
		if _, ok := detectors.NamedIn(known, marker.Name); !ok && marker.Tag == fixture.Sinful && !slices.Contains(unknown, marker.Name) {
			unknown = append(unknown, marker.Name)
		}
	}
	slices.Sort(unknown)
	for _, name := range unknown {
		t.Errorf("the fixture marks %s, which no Go detector finds", name)
	}
}
