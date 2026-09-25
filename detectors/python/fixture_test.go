package python_test

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/fixture"
	_ "github.com/jessegall/code-commandments/registry"
)

// shop is tests/Fixtures/python as the engine reads it, through the real bridge.
func shop(t *testing.T) *engine.Codebase {
	t.Helper()
	root, err := filepath.Abs("../../tests/Fixtures/python")
	if err != nil {
		t.Fatal(err)
	}
	stream, err := bridge.Once(bridge.TestMypy(t), root)
	if err != nil {
		t.Fatal(err)
	}

	return engine.Load(stream)
}

func TestEveryPythonDetectorFlagsExactlyWhatTheFixtureMarks(t *testing.T) {
	fixture.Fixture{Codebase: shop(t), Detectors: detectors.Of(catalog.Python), Resolver: fixture.FileScenarios}.Prove(t)
}

func TestEverySinTheFixtureMarksHasADetector(t *testing.T) {
	codebase := shop(t)
	registered := detectors.Of(catalog.Python)
	var unknown []string
	for _, marker := range fixture.Markers(codebase) {
		if _, ok := detectors.NamedIn(registered, marker.Name); !ok && marker.Tag == fixture.Sinful && !slices.Contains(unknown, marker.Name) {
			unknown = append(unknown, marker.Name)
		}
	}
	slices.Sort(unknown)
	for _, name := range unknown {
		t.Errorf("the fixture marks %s, which no Python detector finds", name)
	}
}
