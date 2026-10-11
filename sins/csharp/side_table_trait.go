package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// SideTableTrait is a trait of a type's registered instances kept in a collection of their names rather than on them.
type SideTableTrait struct{}

func init() {
	sins.Register(catalog.CSharp, SideTableTrait{})
}

// Definition is what the sin states about itself.
func (SideTableTrait) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-side-table-trait",
		Skill:       skills.TellDontAsk{},
		Description: "a trait of a type's registered instances kept as a separate collection of their names, rather than as something each instance answers for itself",
		Rule:        "A trait that is true of some registered instances is answered by the instance; the collection of names it is true of is derived from them, never kept beside them.",
		Suggestion:  "Add the member to the type, give each instance its own answer, and derive the collection from them — or delete it. A side table does not only record the trait in the wrong place: it leaves an instance added later no way to declare it at all.",
	}
}
