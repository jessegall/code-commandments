package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// UselessPropertyHook is the useless-property-hook sin.
type UselessPropertyHook struct{}

func init() { sins.Register(catalog.Backend, UselessPropertyHook{}) }

// Definition is what the sin states about itself.
func (UselessPropertyHook) Definition() sins.Definition {
	return sins.Definition{
		Name:        "useless-property-hook",
		Skill:       skills.TypeHonesty{},
		Description: "A `get` hook that reads nothing from `$this` — a stored property wearing computed syntax",
		Rule:        `A property hook must EARN its hook: a ` + "`" + `get` + "`" + ` body that references no ` + "`" + `$this` + "`" + ` (and no ` + "`" + `parent::` + "`" + `) computes nothing from the object — it yields the same value however the instance is configured, so it is a plain property in disguise. This usually happens when an interface declares ` + "`" + `{ get; }` + "`" + ` and the implementer mimics the syntax; a plain property satisfies a hooked interface property just as well.`,
		Suggestion:  `Make it a stored property: a constant body becomes a property default (` + "`" + `public ?Transition $t = null;` + "`" + `); a constructed value (` + "`" + `get => Transition::make(...)` + "`" + `) is assigned ONCE in the constructor. Keep the hook only when the body genuinely derives from ` + "`" + `$this` + "`" + ` state.`,
	}
}
