package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// GenericThrow is `throw new Exception/InvalidOperationException("…")` — a failure that names nothing, described in prose at the throw site.
type GenericThrow struct{}

func init() {
	sins.Register(catalog.CSharp, GenericThrow{})
}

// Definition is what the sin states about itself.
func (GenericThrow) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-generic-throw",
		Skill:       skills.Exceptions{},
		Description: "`throw new Exception/InvalidOperationException(\"…\")` — a failure that names nothing, described in prose at the throw site",
		Rule:        "Throw a named exception built by a static factory, never a bare `Exception` or `InvalidOperationException` with a message written at the throw.",
		Suggestion:  "Give the failure a class of its own with a static factory that takes the values and writes the message once — `throw UnknownCarrier.Named(name);` — so a caller can catch it by name.",
	}
}
