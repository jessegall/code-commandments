package typescript

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/typescript"
)

// SideTableTrait is a trait of a registered object kept in a collection of their names rather than on the object.
type SideTableTrait struct{}

func init() {
	sins.Register(catalog.TypeScript, SideTableTrait{})
}

// Definition is what the sin states about itself.
func (SideTableTrait) Definition() sins.Definition {
	return sins.Definition{
		Name:        "typescript-side-table-trait",
		Skill:       skills.Duplication{},
		Description: "a trait of the objects a module registers, kept as a separate collection of their names rather than as a field each object carries",
		Rule:        "A trait that is true of some of a module's registered objects is a field on the object; the collection of names it is true of is derived from them, never kept beside them.",
		Suggestion:  "Add the field to the object, give each one its own answer, and derive the collection from them — `const QUIET = new Set(TAGS.filter(t => !t.saidInChat).map(t => t.name))` — or delete it. A side table does not only record the trait in the wrong place: it leaves an object added later no way to declare it at all.",
	}
}
