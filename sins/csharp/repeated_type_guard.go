package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// RepeatedTypeGuard is the same chain of type checks — `node is Invocation call && call.Target is MemberAccess` — written at two or more sites, a shape with no name.
type RepeatedTypeGuard struct{}

func init() {
	sins.Register(catalog.CSharp, RepeatedTypeGuard{})
}

// Definition is what the sin states about itself.
func (RepeatedTypeGuard) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-repeated-type-guard",
		Skill:       skills.RepeatedCallHelper{},
		Description: "the same chain of type checks — `node is Invocation call && call.Target is MemberAccess` — written at two or more sites, a shape with no name",
		Rule:        "Name a shape checked the same way in more than one place once, as a member of the type, and ask for it by name.",
		Suggestion:  "Add a method or property that answers the check — `node.IsMemberCall(out var call)` — and use it at every site.",
	}
}
