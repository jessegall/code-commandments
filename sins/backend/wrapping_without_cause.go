package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// WrappingWithoutCause is the wrapping-without-cause sin.
type WrappingWithoutCause struct{}

func init() { sins.Register(catalog.Backend, WrappingWithoutCause{}) }

// Definition is what the sin states about itself.
func (WrappingWithoutCause) Definition() sins.Definition {
	return sins.Definition{
		Name:        "wrapping-without-cause",
		Skill:       skills.Exceptions{},
		Description: "Wrapping a caught exception without passing it as `previous`/cause",
		Rule:        `When wrapping a caught exception, pass the original as ` + "`" + `previous` + "`" + `/cause — never drop the stack trace.`,
		Suggestion:  "Pass the caught exception as `previous: $e`.",
	}
}
