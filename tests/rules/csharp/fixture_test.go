package csharp_test

import (
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
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
	root, err := filepath.Abs("../../Fixtures/csharp")
	if err != nil {
		t.Fatal(err)
	}
	stream, err := bridge.Once(bridgetest.Roslyn(t, root), bridge.CSharpProjects.Under(root).Arguments()...)
	if err != nil {
		t.Fatal(err)
	}

	return stream
}

func TestNoCSharpRuleCostsMoreThanTheTreeItReadsGrewBy(t *testing.T) {
	stream := parsed(t)

	fixturetest.NoRuleOutgrowsItsTree(t, engine.Load(stream), engine.Load(stream.TwiceOver()), configured(t))
}

func configured(t testing.TB) []rule.Detector {
	return fixturetest.Configured(t, "../../Fixtures/csharp", rule.Of(catalog.CSharp))
}
