package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// BlankStringDefault is a `string` parameter or property defaulted to `""` and then checked with `== ""` or `string.IsNullOrEmpty` — the blank is being used to mean "missing".
type BlankStringDefault struct{}

func init() {
	sins.Register(catalog.CSharp, BlankStringDefault{})
}

// Definition is what the sin states about itself.
func (BlankStringDefault) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-blank-string-default",
		Skill:       skills.Absence{},
		Description: "a `string` parameter or property defaulted to `\"\"` and then checked with `== \"\"` or `string.IsNullOrEmpty` — the blank is being used to mean \"missing\"",
		Rule:        "If a value can be missing, say so in its type with `string?`; don't default it to `\"\"` and then check for the blank.",
		Suggestion:  "Make it `string? note = null` and check `note is null`, so nobody has to know that `\"\"` means \"not given\".",
	}
}
