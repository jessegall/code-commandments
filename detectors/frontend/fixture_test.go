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
		Known:     append(detectors.Of(catalog.Vue), detectors.Of(catalog.TypeScript)...),
		Resolver:  fixture.FileScenarios,
	})
}

const fixtureBudget = 30

func configured() []detectors.Detector {
	var configured []detectors.Detector
	for _, detector := range append(detectors.Of(catalog.Vue), detectors.Of(catalog.TypeScript)...) {
		if budget, ok := detector.(frontenddetectors.ComponentBudgetDetector); ok {
			detector = budget.Elements(fixtureBudget)
		}
		configured = append(configured, detector)
	}

	return configured
}
