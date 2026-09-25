package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// ConstructorSideEffect is a constructor that calls a method on something it was handed and ignores the result — just creating the object changes something outside it.
type ConstructorSideEffect struct{}

func init() {
	sins.Register(catalog.CSharp, ConstructorSideEffect{})
}

// Definition is what the sin states about itself.
func (ConstructorSideEffect) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-constructor-side-effect",
		Skill:       skills.FixAtTheSource{},
		Description: "a constructor that calls a method on something it was handed and ignores the result — just creating the object changes something outside it",
		Rule:        "A constructor sets up the object; creating one should never change anything outside it.",
		Suggestion:  "Keep the collaborator in a field and call it from the method someone actually calls to do the work.",
	}
}
