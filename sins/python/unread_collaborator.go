package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// UnreadCollaborator is a collaborator kept by __init__ under a name of its own and read nowhere — the class asks for something it does not use.
type UnreadCollaborator struct{}

func init() {
	sins.Register(catalog.Python, UnreadCollaborator{})
}

// Definition is what the sin states about itself.
func (UnreadCollaborator) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-unread-collaborator",
		Skill:       skills.FixAtTheSource{},
		Description: "a collaborator `__init__` keeps as an attribute of its own, `self._mailer = mailer`, that nothing anywhere reads — the class asks for something it does not use",
		Rule:        "A constructor asks for what the class uses, and nothing more: a collaborator nothing reads is removed, along with what the callers pass.",
		Suggestion:  "Delete the parameter and the attribute, then follow the callers: whatever they built only to hand it over goes too. If the class is meant to use it and does not, that is the bug the unread attribute is pointing at.",
	}
}
