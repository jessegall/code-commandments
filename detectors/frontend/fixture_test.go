package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
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
		Detectors: append(detectors.Of(catalog.Frontend), detectors.Of(catalog.TypeScript)...),
		Resolver:  fixture.FileScenarios,
	})
}
