package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// EnumCaseOrChain is `s == Status.Paid || s == Status.Refunded` (or `s is Status.Paid or Status.Refunded`) — a group of enum cases tested by hand at the call site.
type EnumCaseOrChain struct{}

func init() {
	sins.Register(catalog.CSharp, EnumCaseOrChain{})
}

// Definition is what the sin states about itself.
func (EnumCaseOrChain) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-enum-case-or-chain",
		Skill:       skills.Enums{},
		Description: "`s == Status.Paid || s == Status.Refunded` (or `s is Status.Paid or Status.Refunded`) — a group of enum cases tested by hand at the call site",
		Rule:        "Give a group of enum cases a name on the enum — an extension method with a `switch` — instead of listing the cases wherever the group is needed.",
		Suggestion:  "Add `public static bool IsSettled(this Status status) => status switch { Status.Paid or Status.Refunded => true, _ => false };` beside the enum, and call `s.IsSettled()`.",
	}
}
