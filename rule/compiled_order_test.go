package rule

import (
	"regexp"
	"testing"
)

func TestTheCheapestChecksAreCompiledFirst(t *testing.T) {
	yes := true
	program := Step{Unused: &yes}
	walk := Step{Inside: &Step{Name: "x"}}
	pattern := Step{NameLike: "get*", pattern: regexp.MustCompile("^get")}
	node := Step{Name: "handle"}

	order := compiled([]Step{program, walk, pattern, node})

	want := []cost{ofTheNode, ofAPattern, ofAWalk, ofTheProgram}
	for at, step := range order {
		if step.cost() != want[at] {
			t.Errorf("step %d costs %d, and %d belongs there", at, step.cost(), want[at])
		}
	}
}

func TestAGroupCostsWhatItsDearestStepCosts(t *testing.T) {
	yes := true
	cheap := Step{All: []Step{{Name: "a"}, {Name: "b"}}}
	dear := Step{Any: []Step{{Name: "a"}, {Unused: &yes}}}

	if cheap.cost() != ofTheNode {
		t.Errorf("a group of comparisons costs %d", cheap.cost())
	}

	if dear.cost() != ofTheProgram {
		t.Errorf("a group holding a program question costs %d", dear.cost())
	}
}

func TestAStepsOfTargetCountsTowardItsCost(t *testing.T) {
	if cost := (Step{Name: "a", Of: "closest:function"}).cost(); cost != ofAWalk {
		t.Errorf("a comparison on a node found by walking costs %d", cost)
	}

	if cost := (Step{Name: "a", Of: "parent"}).cost(); cost != ofTheNode {
		t.Errorf("a comparison on the parent costs %d", cost)
	}
}

func TestStepsInsideAGroupAreCompiledToo(t *testing.T) {
	yes := true
	step := Step{Not: &Step{All: []Step{{Unused: &yes}, {Name: "a"}}}}

	step.compileInside()

	if step.Not.All[0].Name != "a" {
		t.Errorf("the group inside a not was left in the order it was written: %+v", step.Not.All)
	}
}
