package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ListMirrorsFolder is the list-mirrors-folder sin.
type ListMirrorsFolder struct{}

func init() { sins.Register(catalog.PHP, ListMirrorsFolder{}) }

func (ListMirrorsFolder) Definition() sins.Definition {
	return sins.Definition{
		Name:        "list-mirrors-folder",
		Skill:       skills.FixAtTheSource{},
		Description: "A hand-written list of `X::class` entries that holds every class of one type in the folders it draws from: a second copy of what the folders already say",
		Rule:        "What the folders already declare has one source: discover the classes from the folder or their type instead of keeping a list in step by hand.",
		Suggestion:  "Discover the classes (scan the folder for implementers of the shared type, or let each class register itself) and delete the list and any test that keeps it equal to the folder.",
	}
}
