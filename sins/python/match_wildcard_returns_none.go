package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// MatchWildcardReturnsNone is a `match` over an enum's members whose `case _:` returns `None` — a member nobody handled answers nothing instead of failing.
type MatchWildcardReturnsNone struct{}

func init() {
	sins.Register(catalog.Python, MatchWildcardReturnsNone{})
}

// Definition is what the sin states about itself.
func (MatchWildcardReturnsNone) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-match-wildcard-returns-none",
		Skill:       skills.Enums{},
		Description: "a `match` over an enum's members whose `case _:` returns `None` — a member nobody handled answers nothing instead of failing",
		Rule:        "End a `match` over an enum's members with a `case _:` that raises, or handle every member; never let the wildcard return `None`.",
		Suggestion:  "`case _: raise UnhandledStatus.of(status)` — or `assert_never(status)` so the type checker names the member left out.",
	}
}
