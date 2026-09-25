package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ScratchStateRestore is the scratch-state-restore sin.
type ScratchStateRestore struct{}

func init() { sins.Register(catalog.Backend, ScratchStateRestore{}) }

// Definition is what the sin states about itself.
func (ScratchStateRestore) Definition() sins.Definition {
	return sins.Definition{
		Name:        "scratch-state-restore",
		Skill:       skills.TypeHonesty{},
		Description: `Scratch state on ` + "`" + `$this` + "`" + ` — a method that saves one of its own fields to a local and restores it (` + "`" + `$prev = $this->scope; … $this->scope = $prev` + "`" + `), the field really a per-call input`,
		Rule:        `Pass a per-call value as a parameter; don't save-and-restore one of your own fields as scratch state.`,
	}
}
