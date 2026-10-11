package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// SideTableTrait is a trait of a type kept in a collection of its instances' names rather than as a field on the type.
type SideTableTrait struct{}

func init() {
	sins.Register(catalog.Python, SideTableTrait{})
}

// Definition is what the sin states about itself.
func (SideTableTrait) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-side-table-trait",
		Skill:       skills.TellDontAsk{},
		Description: "a trait of a type kept in a collection of its instances' names beside the registry, rather than as a field each instance answers for itself",
		Rule:        "A trait that is true of some instances of a type is a field on the type; the collection of names it is true of is derived from the instances, never kept beside them.",
		Suggestion:  "Add the field to the type, give each instance its own answer, and derive the collection from the instances — `QUIET = {t.name for t in TAGS if not t.said_in_chat}` — or delete it. A side table does not only record the trait in the wrong place: it leaves an instance added later no way to declare it at all.",
	}
}
