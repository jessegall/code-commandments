package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// PositionalTupleReturn is the positional-tuple-return sin.
type PositionalTupleReturn struct{}

func init() { sins.Register(catalog.Backend, PositionalTupleReturn{}) }

// Definition is what the sin states about itself.
func (PositionalTupleReturn) Definition() sins.Definition {
	return sins.Definition{
		Name:        "positional-tuple-return",
		Skill:       skills.ValueObjects{},
		Description: `Returning a positional TUPLE — ` + "`" + `return [$node, $key, $inputs, $outputs]` + "`" + ` — bundling independent values as a keyless list the caller destructures by position`,
		Rule:        "Return a typed object, not a positional tuple `[$a, $b, $c]` the caller destructures by position.",
		Suggestion:  "A small `readonly` result object.",
	}
}
