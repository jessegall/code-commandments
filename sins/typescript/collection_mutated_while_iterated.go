package typescript

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/typescript"
)

// CollectionMutatedWhileIterated is a walk that changes the very collection it walks, so the iterator reads ground that moved.
type CollectionMutatedWhileIterated struct{}

func init() {
	sins.Register(catalog.TypeScript, CollectionMutatedWhileIterated{})
}

// Definition is what the sin states about itself.
func (CollectionMutatedWhileIterated) Definition() sins.Definition {
	return sins.Definition{
		Name:        "typescript-collection-mutated-while-iterated",
		Skill:       skills.Flow{},
		Description: "a `for…of` or `forEach` that splices, shifts, sorts or pops the very array it walks — every element after the change has moved, so the walk skips one or reads one twice",
		Rule:        "A walk counts on the positions it has not reached yet: never move or drop the elements of the array you are walking.",
		Suggestion:  "Decide while you walk and change afterwards — collect what to drop and `filter` it out once the loop is done — or walk a copy and say so: `[...items]`, `items.slice()` and `Array.from(items)` are each a different array, and changing the original while walking one of those is sound.",
	}
}
