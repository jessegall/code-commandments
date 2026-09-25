package php

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestTypeNameReadsEveryWrittenTypeAsPhpDoes(t *testing.T) {
	shop.Parity(t, "typenames", func(answer shop.Answer, node engine.Match) any {
		var ask any
		if err := json.Unmarshal(answer.Ask, &ask); err != nil {
			t.Fatal(err)
		}
		switch ask := ask.(type) {
		case map[string]any:
			pair := ask["overlaps"].([]any)

			return Overlaps(pair[0].(string), pair[1].(string))
		case string:
			switch ask {
			case "promises":
				promises := map[string]bool{}
				for _, scalar := range []string{"string", "int", "bool", "float", "array"} {
					promises[scalar] = PromisesScalar(node, scalar)
				}

				return promises
			case "declared":
				return read(Written(node.Node().Declared))
			case "returns":
				return read(Written(node.Node().Returns))
			}
		}
		t.Fatalf("an unknown ask %s", answer.Ask)

		return nil
	})
}

func read(written TypeName) map[string]any {
	includes, classNames := map[string]bool{}, map[string]bool{}
	for _, name := range written.Names() {
		includes[name] = written.UnionIncludes(name)
		classNames[name] = IsClassName(name)
	}

	return map[string]any{
		"class":           orNil(written.Class()),
		"simpleName":      orNil(written.SimpleName()),
		"nullableClass":   orNil(written.NullableClass()),
		"isNullable":      written.IsNullable(),
		"isNullableArray": written.IsNullableArray(),
		"render":          written.Render(),
		"unionIncludes":   includes,
		"isClassName":     classNames,
	}
}

// orNil is a name, or nil for none: how PHP answers a name it cannot read.
func orNil(name string) any {
	if name == "" {
		return nil
	}

	return name
}
