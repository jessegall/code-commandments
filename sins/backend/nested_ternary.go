package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// NestedTernary is the nested-ternary sin.
type NestedTernary struct{}

func init() { sins.Register(catalog.Backend, NestedTernary{}) }

// Definition is what the sin states about itself.
func (NestedTernary) Definition() sins.Definition {
	return sins.Definition{
		Name:        "nested-ternary",
		Skill:       skills.GuardClausesAndFlow{},
		Description: "Nested/chained ternary `$a ? $b : ($c ? $d : $e)` (hidden control flow)",
		Rule:        `Unfold a nested/chained ternary into a ` + "`" + `match` + "`" + ` or guards; don't hide branching in ` + "`" + `$a ? $b : ($c ? $d : $e)` + "`" + `.`,
		Suggestion:  "A `match`, or early-return guards.",
	}
}
