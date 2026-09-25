package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ConstructorSideEffect is an `__init__` that tells a collaborator to act and throws the answer away — merely building the object has an effect outside it.
type ConstructorSideEffect struct{}

func init() {
	sins.Register(catalog.Python, ConstructorSideEffect{})
}

// Definition is what the sin states about itself.
func (ConstructorSideEffect) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-constructor-side-effect",
		Skill:       skills.FixAtTheSource{},
		Description: "an `__init__` that tells a collaborator to act and throws the answer away — merely building the object has an effect outside it.",
		Rule:        "Let `__init__` establish what the object is; never let building one change anything outside it.",
		Suggestion:  "Keep the collaborator as a field and act on it from the method that someone actually calls.",
	}
}
