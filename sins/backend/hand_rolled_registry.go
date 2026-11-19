package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// HandRolledRegistry is the hand-rolled-registry sin.
type HandRolledRegistry struct{}

func init() { sins.Register(catalog.PHP, HandRolledRegistry{}) }

func (HandRolledRegistry) Definition() sins.Definition {
	return sins.Definition{
		Name:        "hand-rolled-registry",
		Skill:       skills.RoleVocabulary{},
		Description: "A class writes its own keyed store (fill under a key, throw on a missing key) instead of extending the one Registry base",
		Rule:        "A keyed store extends the project's one `Registry` base and states only what it holds; the register/get/has plumbing is written once.",
		Suggestion:  "Extend the `Registry` base (scaffold one if the project has none) and delete the class's own array, `register()` and `get()`.",
	}
}
