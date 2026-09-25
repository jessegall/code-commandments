package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// GenericException is the generic-exception sin.
type GenericException struct{}

func init() { sins.Register(catalog.Backend, GenericException{}) }

// Definition is what the sin states about itself.
func (GenericException) Definition() sins.Definition {
	return sins.Definition{
		Name:        "generic-exception",
		Skill:       skills.Exceptions{},
		Description: "`throw new <bare SPL>` (RuntimeException/LogicException/…) instead of a named type",
		Rule:        "Throw a NAMED domain exception, never a bare SPL `Exception`/`RuntimeException`.",
		Suggestion:  "A named exception class with a static `::for($values)` factory that writes the message.",
	}
}
