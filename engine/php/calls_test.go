package php

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestCallsReachWhatPhpSaysTheyReach(t *testing.T) {
	codebase := shop.Codebase(t)
	types := TypesOf(codebase)
	types.Fill(codebase)
	chains := ChainsOf(codebase)
	shop.Parity(t, "calls", func(answer shop.Answer, node engine.Match) any {
		var ask string
		if err := json.Unmarshal(answer.Ask, &ask); err != nil {
			t.Fatal(err)
		}
		switch ask {
		case "call":
			var callee any
			if target := node.Node().Target; target != nil {
				callee = []string{target.Type, target.Name}
			}

			return map[string]any{"receiver": orNil(ReceiverTypeOf(node)), "callee": callee, "name": orNil(CallName(node))}
		case "chain":
			method := node.Parent()
			for method.Kind() != "Stmt_ClassMethod" {
				method = method.Parent()
			}

			return orNil(chains.Resolve(node, ParamTypes(method)))
		}
		t.Fatalf("an unknown ask %s", answer.Ask)

		return nil
	})
}
