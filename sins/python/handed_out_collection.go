package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// HandedOutCollection is a getter returning the class's own mutable collection, untouched — every caller then holds the insides and may change them without asking.
type HandedOutCollection struct{}

func init() {
	sins.Register(catalog.Python, HandedOutCollection{})
}

// Definition is what the sin states about itself.
func (HandedOutCollection) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-handed-out-collection",
		Skill:       skills.ValueObjects{},
		Description: "a getter whose whole body returns the class's own `list`, `dict` or `set`, untouched — every caller holds the insides and may add to or take from them without the class knowing",
		Rule:        "A class keeps its own collection: hand out something a caller cannot change, or a copy, never the thing itself.",
		Suggestion:  "Return a `tuple(...)`, a `list(...)` copy, or annotate the return as a `Sequence`/`Mapping` so the caller cannot change what it holds; and give the class the methods callers were reaching in to do.",
	}
}
