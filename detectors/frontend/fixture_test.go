package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	bridge "github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/fixture"
	_ "github.com/jessegall/code-commandments/registry"
)

func TestTheFrontendFixtureProvesEveryDetector(t *testing.T) {
	codebase, err := bridge.Here().Scan("../../tests/Fixtures/frontend")
	if err != nil {
		t.Fatal(err)
	}
	fixture.Fixture{
		Codebase:  codebase,
		Detectors: append(detectors.Of(catalog.Frontend), detectors.Of(catalog.TypeScript)...),
		Resolver:  fixture.FileScenarios,
	}.Prove(t)
}
