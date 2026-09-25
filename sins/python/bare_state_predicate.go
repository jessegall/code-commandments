package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// BareStatePredicate is a `bool` about the object's own state named as a bare verb — `binds()`, `spins` — where a question belongs.
type BareStatePredicate struct{}

func init() {
	sins.Register(catalog.Python, BareStatePredicate{})
}

// Definition is what the sin states about itself.
func (BareStatePredicate) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-bare-state-predicate",
		Skill:       skills.MethodMood{},
		Description: "a `bool` about the object's own state named as a bare verb — `binds()`, `spins` — where a question belongs",
		Rule:        "Name a `bool` about the object itself as a question: `is_bound()`, `has_parent()`, `can_retry()`.",
		Suggestion:  "Make it a question: `is_…`, `has_…`, `can_…`, `awaits_…`.",
	}
}
