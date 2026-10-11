package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// SideTableTrait is an enum keeping a trait of some of its cases in a constant list of them, rather than on the case.
type SideTableTrait struct{}

func init() { sins.Register(catalog.Backend, SideTableTrait{}) }

// Definition is what the sin states about itself.
func (SideTableTrait) Definition() sins.Definition {
	return sins.Definition{
		Name:        "side-table-trait",
		Skill:       skills.EnumsWithBehaviour{},
		Description: "An enum holding a trait of some of its cases as a constant list of those cases, instead of as something each case answers for itself.",
		Rule:        "A trait that is true of some cases is answered by the case, never kept beside them as a list of the ones it is true of.",
		Suggestion:  "Give the enum a method that answers the trait per case (`match ($this) { self::Reply, self::Log => false, default => true }`), and delete the list — a case added later then has to answer, where a list leaves it silently taking the default with nowhere to say otherwise.",
	}
}
