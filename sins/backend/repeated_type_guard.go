package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RepeatedTypeGuard is the repeated-type-guard sin.
type RepeatedTypeGuard struct{}

func init() { sins.Register(catalog.Backend, RepeatedTypeGuard{}) }

// Definition is what the sin states about itself.
func (RepeatedTypeGuard) Definition() sins.Definition {
	return sins.Definition{
		Name:        "repeated-type-guard",
		Skill:       skills.RepeatedCallHelper{},
		Description: `The SAME multi-` + "`" + `instanceof` + "`" + ` type-narrowing guard (` + "`" + `$x instanceof A && $x->y instanceof B` + "`" + `) is written verbatim in ≥2 places — a check with no name, copied instead of named`,
		Rule:        `Promote a recurring ` + "`" + `instanceof` + "`" + ` chain to a named predicate (` + "`" + `$x->isNewOfNamedClass()` + "`" + `), so the intent has a name and the narrowing has ONE home.`,
		Suggestion:  "Extract the repeated `instanceof` chain into a named boolean method and call THAT at each site.",
	}
}
