package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// UnreadCollaborator is a collaborator taken through the constructor, kept private, and never read — the class asks for something it does not use.
type UnreadCollaborator struct{}

func init() {
	sins.Register(catalog.PHP, UnreadCollaborator{})
}

// Definition is what the sin states about itself.
func (UnreadCollaborator) Definition() sins.Definition {
	return sins.Definition{
		Name:        "unread-collaborator",
		Skill:       skills.FixAtTheSource{},
		Description: "a collaborator promoted into a private field by the constructor and never read anywhere in the class — the class asks for something it does not use, and only a search of the whole type says so",
		Rule:        "A constructor asks for what the class uses, and nothing more: a collaborator no method reads is removed, along with what the callers pass.",
		Suggestion:  "Delete the parameter and the field, then follow the callers: whatever they built or resolved only to hand it over goes too. If the class is meant to use it and does not, that is the bug the empty field is pointing at.",
	}
}
