package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// WrappingWithoutCause is a `catch` that throws a new exception without passing the caught one as its inner exception, so the original stack trace is lost.
type WrappingWithoutCause struct{}

func init() {
	sins.Register(catalog.CSharp, WrappingWithoutCause{})
}

// Definition is what the sin states about itself.
func (WrappingWithoutCause) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-wrapping-without-cause",
		Skill:       skills.Exceptions{},
		Description: "a `catch` that throws a new exception without passing the caught one as its inner exception, so the original stack trace is lost",
		Rule:        "When you wrap a caught exception, pass it on as the inner exception; never throw away the original.",
		Suggestion:  "Catch it into a variable and pass it on — `catch (IOException e) { throw new LoadFailed(\"…\", e); }` — or rethrow with `throw;`.",
	}
}
