package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// HandedOutCollection is a getter returning the type's own mutable collection, untouched — every caller then holds the insides and may change them without asking.
type HandedOutCollection struct{}

func init() {
	sins.Register(catalog.CSharp, HandedOutCollection{})
}

// Definition is what the sin states about itself.
func (HandedOutCollection) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-handed-out-collection",
		Skill:       skills.ValueObjects{},
		Description: "a property or method whose whole body returns the type's own `List`, `Dictionary` or `HashSet`, untouched — every caller holds the insides and may add to or take from them without the type knowing",
		Rule:        "A type keeps its own collection: hand out something a caller cannot change, or a copy, never the thing itself.",
		Suggestion:  "Declare the return as `IReadOnlyList<T>` or `IReadOnlyDictionary<K, V>`, or answer with a copy, and give the type the methods callers were reaching in to do.",
	}
}
