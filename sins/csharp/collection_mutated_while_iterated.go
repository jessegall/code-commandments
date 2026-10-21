package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// CollectionMutatedWhileIterated is a `foreach` that adds to or removes from the very collection it walks — the enumerator reads a collection that moved under it and throws.
type CollectionMutatedWhileIterated struct{}

func init() {
	sins.Register(catalog.CSharp, CollectionMutatedWhileIterated{})
}

// Definition is what the sin states about itself.
func (CollectionMutatedWhileIterated) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-collection-mutated-while-iterated",
		Skill:       skills.Flow{},
		Description: "a `foreach` that adds to or removes from the very collection it walks — the enumerator reads a collection that moved under it, which throws InvalidOperationException",
		Rule:        "Never change the collection a `foreach` walks while it walks it: decide inside the loop and change afterwards.",
		Suggestion:  "Collect what the loop decided in a list of its own and apply it after the loop, or walk a copy (`foreach (var item in items.ToList())`) when the change must happen as it goes.",
	}
}
