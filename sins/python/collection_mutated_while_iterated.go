package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// CollectionMutatedWhileIterated is a loop that adds to or removes from the very collection it walks — the iterator reads a sequence that moved under it.
type CollectionMutatedWhileIterated struct{}

func init() {
	sins.Register(catalog.Python, CollectionMutatedWhileIterated{})
}

// Definition is what the sin states about itself.
func (CollectionMutatedWhileIterated) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-collection-mutated-while-iterated",
		Skill:       skills.Flow{},
		Description: "a loop that appends to, removes from or deletes out of the very collection it walks — the iterator reads a sequence that moved under it, so an element is skipped or it raises",
		Rule:        "Never change the collection a loop walks while it walks it: decide inside the loop and change afterwards.",
		Suggestion:  "Collect what the loop decided in a list of its own and apply it after the loop, or walk a copy (`for item in list(items):`) when the change must happen as it goes.",
	}
}
