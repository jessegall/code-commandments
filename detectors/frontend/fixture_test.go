package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	frontenddetectors "github.com/jessegall/code-commandments/detectors/frontend"
	tsdetectors "github.com/jessegall/code-commandments/detectors/typescript"
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
	codebase.Rooted(shop)
	fixturetest.Prove(t, fixture.Fixture{
		Codebase:  codebase,
		Detectors: configured(),
		Known:     append(detectors.Of(catalog.Vue), detectors.Of(catalog.TypeScript)...),
		Resolver:  fixture.FileScenarios,
	})
}

const fixtureBudget = 30

func TestNoFrontendRuleCostsMoreThanTheTreeItReadsGrewBy(t *testing.T) {
	frontend, err := bridge.Here().Stream(shop)
	if err != nil {
		t.Fatal(err)
	}
	server, err := php.Here().Stream(shop)
	if err != nil {
		t.Fatal(err)
	}

	small := engine.Load(frontend, server)
	large := engine.Load(frontend.TwiceOver(), server.TwiceOver())

	fixturetest.NoRuleOutgrowsItsTree(t, small, large, configured())
}

func configured() []detectors.Detector {
	dependencies := tsdetectors.NamespaceDependencyDetector.
		Layer("client/layers/inventory/core").
		Layer("client/layers/inventory/view", "client/layers/inventory/core").
		Layer("client/layers/payroll/core").
		Layer("client/layers/payroll/view", "client/layers/payroll/core").
		Layer("client/layers/routing/core").
		Layer("client/layers/routing/view", "client/layers/routing/core")
	var configured []detectors.Detector
	for _, detector := range append(detectors.Of(catalog.Vue), detectors.Of(catalog.TypeScript)...) {
		if budget, ok := detector.(frontenddetectors.ComponentBudgetDetector); ok {
			detector = budget.Elements(fixtureBudget)
		}
		if detector.Sin() == dependencies.Sin() {
			detector = dependencies
		}
		configured = append(configured, detector)
	}

	return configured
}
