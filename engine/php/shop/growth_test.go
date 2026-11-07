package shop_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/shop"
	"github.com/jessegall/code-commandments/fixture"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

func TestNoRuleCostsMoreThanTheTreeItReadsGrewBy(t *testing.T) {
	small := shop.Project(t)
	large := shop.Doubled(t)

	var rules []detectors.Detector
	for _, rule := range detectors.Of(catalog.PHP) {
		rules = append(rules, rule)
	}

	if len(rules) == 0 {
		t.Fatal("no PHP rule was registered, so this test would pass by looking at nothing")
	}

	grown := fixture.Grown(small, large, rules)

	for _, each := range grown[:min(5, len(grown))] {
		t.Logf("%s cost %d points over the fixture and %d over the same fixture twice, %.1f times",
			each.Detector, each.Small, each.Large, each.Times)
	}

	for _, each := range grown {
		if each.Times > 3 {
			t.Errorf("%s cost %d points over the fixture and %d over the same fixture twice, %.1f times, where "+
				"twice the tree may cost about twice the compute: a rule that grows faster than the project is one "+
				"a consumer cannot escape", each.Detector, each.Small, each.Large, each.Times)
		}
	}
}

// everyFileAgainstEveryOther is a rule written the way the gate exists to catch: for each class it walks the whole
// project again, so twice the tree costs four times the compute.
type everyFileAgainstEveryOther struct{}

func (everyFileAgainstEveryOther) Sin() sins.Sin { return backendsins.DeepNesting{} }

func (everyFileAgainstEveryOther) Find(codebase *engine.Codebase) []engine.Match {
	var found []engine.Match

	for _, class := range php.In(codebase).WhereKind("Stmt_Class").Get() {
		for _, other := range php.In(codebase).WhereKind("Stmt_Class").Get() {
			if class.Name() == other.Name() && class.File() != other.File() {
				found = append(found, class)
			}
		}
	}

	return found
}

func TestTheGateCatchesARuleThatGrowsFasterThanTheTree(t *testing.T) {
	grown := fixture.Grown(shop.Project(t), shop.Doubled(t), []detectors.Detector{everyFileAgainstEveryOther{}})

	if len(grown) != 1 {
		t.Fatalf("the gate measured %d rules of 1", len(grown))
	}

	if grown[0].Times <= 3 {
		t.Errorf("a rule that walks the whole project for every class cost %.1f times over twice the tree, and the "+
			"gate lets anything up to 3 through: it would not catch this", grown[0].Times)
	}

	t.Logf("a rule that walks the project per class cost %d points over the fixture and %d over twice it, %.1f times",
		grown[0].Small, grown[0].Large, grown[0].Times)
}
