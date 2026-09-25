package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// FeatureEnvy is a method that loops another object's collection or writes its fields, reaching into it more than into its own state — behaviour exiled from the object it works on.
type FeatureEnvy struct{}

func init() {
	sins.Register(catalog.Python, FeatureEnvy{})
}

// Definition is what the sin states about itself.
func (FeatureEnvy) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-feature-envy",
		Skill:       skills.TellDontAsk{},
		Description: "a method that loops another object's collection or writes its fields, reaching into it more than into its own state — behaviour exiled from the object it works on",
		Rule:        "Move the behaviour onto the object whose data it works on; ask it (`order.heaviest_line()`), don't reach through it.",
		Suggestion:  "Move the method onto the envied class and call it there; keep only the orchestration here.",
	}
}
