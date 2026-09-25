package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RepeatedNamedCall is the repeated-named-call sin.
type RepeatedNamedCall struct{}

func init() { sins.Register(catalog.Backend, RepeatedNamedCall{}) }

// Definition is what the sin states about itself.
func (RepeatedNamedCall) Definition() sins.Definition {
	return sins.Definition{
		Name:        "repeated-named-call",
		Skill:       skills.RepeatedCallHelper{},
		Description: `The same ` + "`" + `with` + "`" + `-style (variadic) method is called with the same named argument at 2+ sites, instead of a named helper on the type`,
		Rule:        `Promote a repeated ` + "`" + `->with…(named: …)` + "`" + ` call into a method on the receiver's type that hides the call and its construction boilerplate.`,
		Suggestion:  `` + "`" + `$element->withMetadata($payload)` + "`" + ` — a ` + "`" + `withMetadata()` + "`" + ` on the type doing ` + "`" + `copyWith(metadata: $payload->toArray())` + "`" + `.`,
	}
}
