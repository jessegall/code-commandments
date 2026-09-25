package csharp_test

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	csdetectors "github.com/jessegall/code-commandments/detectors/csharp"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/fixture"
	_ "github.com/jessegall/code-commandments/registry"
)

// shop is tests/Fixtures/csharp as the engine reads it, through the real bridge.
func shop(t *testing.T) *engine.Codebase {
	t.Helper()
	root, err := filepath.Abs("../../tests/Fixtures/csharp")
	if err != nil {
		t.Fatal(err)
	}
	stream, err := bridge.Once(bridge.TestRoslyn(t), root)
	if err != nil {
		t.Fatal(err)
	}

	return engine.Load(stream)
}

// configured is the C# detectors as tests/Fixtures/csharp/.commandments/config.php configures them: the shop
// declares its layers.
func configured() []detectors.Detector {
	var configured []detectors.Detector
	for _, detector := range detectors.Of(catalog.CSharp) {
		if _, ok := detector.(csdetectors.NamespaceDependencyDetector); ok {
			detector = csdetectors.NamespaceDependencyDetector{}.
				Layer("Shop.Catalog").
				Layer("Shop.Search", "Shop.Catalog").
				Layer("Shop.Checkout", "Shop.Catalog").
				Layer("Shop.Storefront", "Shop.Search", "Shop.Catalog")
		}
		configured = append(configured, detector)
	}

	return configured
}

func TestEveryCSharpDetectorFlagsExactlyWhatTheFixtureMarks(t *testing.T) {
	fixture.Fixture{Codebase: shop(t), Detectors: configured(), Known: detectors.Of(catalog.CSharp), Resolver: fixture.FileScenarios}.Prove(t)
}

func TestEverySinTheFixtureMarksHasADetector(t *testing.T) {
	codebase := shop(t)
	registered := detectors.Of(catalog.CSharp)
	var unknown []string
	for _, marker := range fixture.Markers(codebase) {
		if _, ok := detectors.NamedIn(registered, marker.Name); !ok && marker.Tag == fixture.Sinful && !slices.Contains(unknown, marker.Name) {
			unknown = append(unknown, marker.Name)
		}
	}
	slices.Sort(unknown)
	for _, name := range unknown {
		t.Errorf("the fixture marks %s, which no C# detector finds", name)
	}
}
