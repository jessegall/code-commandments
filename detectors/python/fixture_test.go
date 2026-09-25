package python_test

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	pydetectors "github.com/jessegall/code-commandments/detectors/python"
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

// configured is the Python detectors as tests/Fixtures/python/.commandments/config.php configures them: the shop
// declares its layers.
func configured() []detectors.Detector {
	var configured []detectors.Detector
	for _, detector := range detectors.Of(catalog.Python) {
		if _, ok := detector.(pydetectors.NamespaceDependencyDetector); ok {
			detector = pydetectors.NamespaceDependencyDetector{}.
				Layer("shop.layout").
				Layer("shop.widgets", "shop.layout").
				Layer("shop.screens", "shop.widgets", "shop.layout").
				Layer("shop.menus", "shop.layout")
		}
		configured = append(configured, detector)
	}

	return configured
}

func TestEveryPythonDetectorFlagsExactlyWhatTheFixtureMarks(t *testing.T) {
	fixture.Fixture{Codebase: shop(t), Detectors: configured(), Known: detectors.Of(catalog.Python), Resolver: fixture.FileScenarios}.Prove(t)
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
