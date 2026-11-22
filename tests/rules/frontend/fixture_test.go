package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	bridge "github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/fixture/fixturetest"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/rule"
)

const shop = "../../tests/Fixtures/frontend"

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

	fixturetest.NoRuleOutgrowsItsTree(t, small, large, configured(t))
}

func configured(t testing.TB) []rule.Detector {
	return fixturetest.Configured(t, shop, rule.OnePerSin(append(rule.Of(catalog.Vue), rule.Of(catalog.TypeScript)...)))
}
