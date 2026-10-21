package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// UnreadCollaborator is a collaborator the type takes and never reads — a private readonly field, or a primary constructor parameter, whose name nothing in the type names again.
type UnreadCollaborator struct{}

func init() {
	sins.Register(catalog.CSharp, UnreadCollaborator{})
}

// Definition is what the sin states about itself.
func (UnreadCollaborator) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-unread-collaborator",
		Skill:       skills.FixAtTheSource{},
		Description: "a private readonly field, or a primary constructor parameter, that nothing in the type reads — the type asks for something it does not use",
		Rule:        "A constructor asks for what the type uses, and nothing more: a collaborator nothing reads is removed, along with what the callers pass.",
		Suggestion:  "Delete the parameter and the field, then follow the callers: whatever they resolved or built only to hand it over goes too. If the type is meant to use it and does not, that is the bug the unread field is pointing at.",
	}
}
