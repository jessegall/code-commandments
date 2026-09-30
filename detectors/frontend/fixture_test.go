package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	frontenddetectors "github.com/jessegall/code-commandments/detectors/frontend"
	"github.com/jessegall/code-commandments/engine"
	bridge "github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/fixture/fixturetest"
	_ "github.com/jessegall/code-commandments/registry"
)

// shop is the frontend fixture: Vue and TypeScript, and the PHP server whose types it must not copy.
const shop = "../../tests/Fixtures/frontend"

func TestTheFrontendFixtureProvesEveryDetector(t *testing.T) {
	frontend, err := bridge.Here().Stream(shop)
	if err != nil {
		t.Fatal(err)
	}
	server, err := php.Here().Stream(shop)
	if err != nil {
		t.Fatal(err)
	}
	codebase := engine.Load(frontend, server)
	fixturetest.Prove(t, fixture.Fixture{
		Codebase:  codebase,
		Detectors: configured(),
		Known:     append(detectors.Of(catalog.Frontend), detectors.Of(catalog.TypeScript)...),
		Resolver:  fixture.FileScenarios,
	})
}

// fixtureBudget is how many elements the shop declares a component's template may render.
const fixtureBudget = 30

// configured is the frontend and TypeScript detectors as the shop declares them: a component budget of its own.
func configured() []detectors.Detector {
	var configured []detectors.Detector
	for _, detector := range append(detectors.Of(catalog.Frontend), detectors.Of(catalog.TypeScript)...) {
		if budget, ok := detector.(frontenddetectors.ComponentBudgetDetector); ok {
			detector = budget.Elements(fixtureBudget)
		}
		configured = append(configured, detector)
	}

	return configured
}
