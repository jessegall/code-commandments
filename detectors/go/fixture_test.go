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
	"github.com/jessegall/code-commandments/engine"
	_ "github.com/jessegall/code-commandments/engine/golang"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/fixture/fixturetest"
	_ "github.com/jessegall/code-commandments/registry"
)

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

func TestEveryGoDetectorFlagsExactlyWhatTheFixtureMarks(t *testing.T) {
	known := detectors.Of(catalog.Go)

	fixturetest.Prove(t, fixture.Fixture{Codebase: shop(t), Detectors: known, Known: known})
}

func TestEverySinTheGoFixtureMarksHasADetector(t *testing.T) {
	codebase := shop(t)
	known := detectors.Of(catalog.Go)

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
