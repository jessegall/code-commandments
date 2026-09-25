package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// TypeSwitch is `shape switch { Circle c => …, Square s => … }` — asking which of your own types a value is, to decide what to do with it.
type TypeSwitch struct{}

func init() {
	sins.Register(catalog.CSharp, TypeSwitch{})
}

// Definition is what the sin states about itself.
func (TypeSwitch) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-type-switch",
		Skill:       skills.TellDontAsk{},
		Description: "`shape switch { Circle c => …, Square s => … }` — asking which of your own types a value is, to decide what to do with it",
		Rule:        "Give the base type a member each type answers, and call it; don't switch on which type a value is.",
		Suggestion:  "Declare `public abstract double Area();` on `Shape`, implement it on `Circle` and `Square`, and write `shape.Area()`.",
	}
}
