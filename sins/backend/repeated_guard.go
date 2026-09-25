package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RepeatedGuard is the repeated-guard sin.
type RepeatedGuard struct{}

func init() { sins.Register(catalog.Backend, RepeatedGuard{}) }

// Definition is what the sin states about itself.
func (RepeatedGuard) Definition() sins.Definition {
	return sins.Definition{
		Name:        "repeated-guard",
		Skill:       skills.RepeatedCallHelper{},
		Description: `The SAME compound guard condition recurs in ≥2 places — the same check spelled differently (inline reaches vs locals) or reordered still counts, so a copied condition has no name`,
		Rule:        `Promote a recurring compound guard to a named predicate. The same condition — however its conjuncts are ordered, and whether it reads ` + "`" + `$obj->x` + "`" + ` inline or a local aliased from it — belongs in ONE named method.`,
		Suggestion:  "Extract the repeated condition into a named boolean method and call THAT at each site.",
	}
}
