package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// UnnamedCondition is a condition of three or more parts asking something of two or more objects — a concept the code never named.
type UnnamedCondition struct{}

func init() {
	sins.Register(catalog.Python, UnnamedCondition{})
}

// Definition is what the sin states about itself.
func (UnnamedCondition) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-unnamed-condition",
		Skill:       skills.Flow{},
		Description: "`if settings.auto and not self.said and event.at > self.since:` — three parts over two objects, so the reader works out what the condition MEANS instead of reading it",
		Rule:        "A condition that takes a reader more than a glance is a concept: give it a name and ask for it by that name.",
		Suggestion:  "Move the condition onto the class that owns most of it, as a method or a `@property` whose name says what it decides — `if self.should_announce(event):` — and let each object answer for its own part.",
	}
}
