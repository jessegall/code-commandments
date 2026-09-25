package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// BareStatePredicate is a `bool` about the object's own state named as a claim — `Binds()`, `Spins` — where a question belongs.
type BareStatePredicate struct{}

func init() {
	sins.Register(catalog.CSharp, BareStatePredicate{})
}

// Definition is what the sin states about itself.
func (BareStatePredicate) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-bare-state-predicate",
		Skill:       skills.MethodMood{},
		Description: "a `bool` about the object's own state named as a claim — `Binds()`, `Spins` — where a question belongs",
		Rule:        "Name a `bool` about the object itself as a question: `IsBound`, `IsSpinning`, `HasParent`, `CanRetry`.",
		Suggestion:  "Rename it to a question — `Binds()` becomes `IsBound`, usually as a property.",
	}
}
