package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// DanglingDocReference is a `<see cref>` that resolves to nothing from where it is written — a name the project no longer declares, or one spelled so it does not reach it.
type DanglingDocReference struct{}

func init() {
	sins.Register(catalog.CSharp, DanglingDocReference{})
}

// Definition is what the sin states about itself.
func (DanglingDocReference) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-dangling-doc-reference",
		Skill:       skills.Documentation{},
		Description: "a `<see cref>` that resolves to nothing from where it is written — a name the project no longer declares, or one spelled so it does not reach it",
		Rule:        "A `cref` must resolve: name what the code is called now, spelled so it reaches it from here, or delete it.",
		Suggestion:  "Point the `cref` at what the name became, qualified or imported so it resolves here (the compiler warns CS1574 until it does); if nothing replaced it, drop the reference.",
	}
}
