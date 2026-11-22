package python_test

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/fixture/fixturetest"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/rule"
)

func shop(t *testing.T) *engine.Codebase {
	t.Helper()

	return engine.Load(parsed(t))
}

func parsed(t *testing.T) *contract.Stream {
	t.Helper()
	root, err := filepath.Abs("../../tests/Fixtures/python")
	if err != nil {
		t.Fatal(err)
	}
	stream, err := bridge.Once(bridgetest.Mypy(t), bridge.Listed(root)...)
	if err != nil {
		t.Fatal(err)
	}

	return stream
}

func TestNoPythonRuleCostsMoreThanTheTreeItReadsGrewBy(t *testing.T) {
	stream := parsed(t)

	fixturetest.NoRuleOutgrowsItsTree(t, engine.Load(stream), engine.Load(stream.TwiceOver()), configured(t))
}

func configured(t testing.TB) []rule.Detector {
	return fixturetest.Configured(t, "../../tests/Fixtures/python", rule.Of(catalog.Python))
}

func TestUndeclaredMemberRecognizesTheDeclaredContract(t *testing.T) {
	stream, err := bridge.Once(bridgetest.Mypy(t), bridge.Listed("testdata/undeclared_member")...)
	if err != nil {
		t.Fatal(err)
	}
	fixturetest.Prove(t, fixture.Fixture{
		Codebase:  engine.Load(stream),
		Detectors: fixture.Proven([]rule.Detector{shipped(t, "undeclared-member")}),
		Known:     fixture.Proven(rule.Every(catalog.Python)),
		Resolver:  fixture.FileScenarios,
	})
}
