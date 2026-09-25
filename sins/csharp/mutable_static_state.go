package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// MutableStaticState is a `static` field that methods write to — state no instance owns, changed by whichever code ran last.
type MutableStaticState struct{}

func init() {
	sins.Register(catalog.CSharp, MutableStaticState{})
}

// Definition is what the sin states about itself.
func (MutableStaticState) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-mutable-static-state",
		Skill:       skills.FixAtTheSource{},
		Description: "a `static` field that methods write to — state no instance owns, changed by whichever code ran last",
		Rule:        "Keep state that changes on an instance someone owns and passes around; don't write to a static field from a method.",
		Suggestion:  "Move the field onto an object and hand that object to the code that reads and changes it; for a value computed once, use a `static readonly Lazy<T>`.",
	}
}
