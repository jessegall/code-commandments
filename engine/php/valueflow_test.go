package php

import (
	"encoding/json"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestValueFlowJudgesEveryFieldAsPhpDoes(t *testing.T) {
	flow := ValueFlowOf(shop.Codebase(t))
	shop.Parity(t, "valueflow", func(answer shop.Answer, class engine.Match) any {
		var field string
		if err := json.Unmarshal(answer.Ask, &field); err != nil {
			t.Fatal(err)
		}
		fqcn := class.Node().Symbol
		verdict := flow.Verdict(fqcn, field)
		assume, guard := flow.Explain(fqcn, field)
		chain := flow.ChainPath(fqcn, field)
		if chain == nil {
			chain = []string{}
		}

		return map[string]any{"assume": verdict.Assume, "guard": verdict.Guard, "assumed": located(assume), "guarded": located(guard), "chain": chain}
	})
}

func located(matches []engine.Match) []string {
	locations := []string{}
	for _, match := range matches {
		locations = append(locations, strings.TrimPrefix(Location(match), shop.Root+"/"))
	}

	return locations
}
