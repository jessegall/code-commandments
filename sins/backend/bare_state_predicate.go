package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// BareStatePredicate is the bare-state-predicate sin.
type BareStatePredicate struct{}

func init() { sins.Register(catalog.Backend, BareStatePredicate{}) }

// Definition is what the sin states about itself.
func (BareStatePredicate) Definition() sins.Definition {
	return sins.Definition{
		Name:        "bare-state-predicate",
		Skill:       skills.MethodMood{},
		Description: `A ` + "`" + `bool` + "`" + ` about the object's own state named as a bare verb — ` + "`" + `binds()` + "`" + `, ` + "`" + `spins()` + "`" + ` — where a question belongs`,
		Rule:        `A ` + "`" + `bool` + "`" + ` answering about the object itself wears a question: ` + "`" + `isBound()` + "`" + `, ` + "`" + `isSpinning()` + "`" + `, ` + "`" + `hasParent()` + "`" + `, ` + "`" + `awaitsAnswer()` + "`" + `. (A predicate that takes what it compares against — ` + "`" + `contains(\$item)` + "`" + `, ` + "`" + `matches(\$name)` + "`" + ` — is already a sentence and stays as it is.)`,
		Suggestion:  "make it a question: `is…`, `has…`, `can…`, `awaits…`",
	}
}
