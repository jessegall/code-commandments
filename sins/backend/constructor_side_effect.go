package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ConstructorSideEffect is the constructor-side-effect sin.
type ConstructorSideEffect struct{}

func init() { sins.Register(catalog.Backend, ConstructorSideEffect{}) }

// Definition is what the sin states about itself.
func (ConstructorSideEffect) Definition() sins.Definition {
	return sins.Definition{
		Name:        "constructor-side-effect",
		Skill:       skills.FixAtTheSource{},
		Description: `A constructor that performs a side effect on a collaborator and throws away the result, so simply creating the object changes something outside it.`,
		Rule:        "Let a constructor establish what the object IS; never let building one change anything outside it.",
		Suggestion:  "Keep the collaborator as a field and act on it from the method that someone actually calls.",
	}
}
