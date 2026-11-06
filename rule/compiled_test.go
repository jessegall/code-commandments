package rule

import (
	"testing"
)

func TestARuleIsCompiledSoItsCheapestQuestionIsAskedFirst(t *testing.T) {
	order := compiled([]Step{
		{ReferredFrom: &Step{Kind: "Call"}},
		{Descendant: &Descended{}},
		{NameLike: "Probe*"},
	})

	if len(order) != 3 {
		t.Fatalf("the rule was compiled to %d steps of 3", len(order))
	}

	if order[0].NameLike == "" {
		t.Errorf("the first question asked is not the cheapest: %+v", order[0])
	}

	if order[1].Descendant == nil {
		t.Errorf("the second question asked is not the one that walks: %+v", order[1])
	}

	if order[2].ReferredFrom == nil {
		t.Errorf("the last question asked is not the one only the whole program can answer: %+v", order[2])
	}
}

func TestEveryStepSaysWhatItCosts(t *testing.T) {
	for _, told := range []struct {
		what string
		step Step
		cost cost
	}{
		{"a name", Step{Name: "total"}, ofTheNode},
		{"a kind", Step{Kind: "FunctionDef"}, ofTheNode},
		{"a modifier", Step{HasModifier: "public"}, ofTheNode},
		{"a descendant", Step{Descendant: &Descended{}}, ofAWalk},
		{"a body", Step{Body: &Step{}}, ofAWalk},
		{"its callers", Step{Callers: &Bounds{}}, ofTheProgram},
		{"where it is referred from", Step{ReferredFrom: &Step{}}, ofTheProgram},
		{"a layer", Step{Layer: "App"}, ofTheProgram},
	} {
		if told.step.cost() != told.cost {
			t.Errorf("%s costs %d, and it is %d", told.what, told.step.cost(), told.cost)
		}
	}
}
