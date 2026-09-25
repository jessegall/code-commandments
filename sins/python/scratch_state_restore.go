package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ScratchStateRestore is `previous = self.scope … self.scope = previous` — an attribute used as per-call scratch, saved and restored around the call.
type ScratchStateRestore struct{}

func init() {
	sins.Register(catalog.Python, ScratchStateRestore{})
}

// Definition is what the sin states about itself.
func (ScratchStateRestore) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-scratch-state-restore",
		Skill:       skills.TypeHonesty{},
		Description: "`previous = self.scope … self.scope = previous` — an attribute used as per-call scratch, saved and restored around the call",
		Rule:        "Pass a per-call value as a parameter; don't save and restore one of your own attributes around the call.",
		Suggestion:  "Hand the value down as an argument — or a small per-call object — and the attribute, its save and its restore disappear.",
	}
}
