package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ConditionalArraySpread is the conditional-array-spread sin.
type ConditionalArraySpread struct{}

func init() { sins.Register(catalog.Backend, ConditionalArraySpread{}) }

// Definition is what the sin states about itself.
func (ConditionalArraySpread) Definition() sins.Definition {
	return sins.Definition{
		Name:        "conditional-array-spread",
		Skill:       skills.Absence{},
		Description: `An array built by spreading a conditional element — ` + "`" + `...($x ? ['k' => $x] : [])` + "`" + ` or ` + "`" + `array_merge($base, $cond ? [...] : [])` + "`" + ` — a ternary-and-empty-array trick that really just means "include this when the value is present."`,
		Rule:        `Don't spread a ` + "`" + `cond ? [...] : []` + "`" + ` to conditionally include a key. Give the target a null-dropping variadic factory (` + "`" + `::of(mixed ...$values)` + "`" + ` that filters out nulls) and pass the value as a named arg — an absent one vanishes with no ternary.`,
		Suggestion:  `Replace ` + "`" + `[...$base, ...($x !== null ? ['k' => $x] : [])]` + "`" + ` with a ` + "`" + `::of(k: $x, …)` + "`" + ` factory that drops null-valued arguments.`,
	}
}
