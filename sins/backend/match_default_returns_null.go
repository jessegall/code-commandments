package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// MatchDefaultReturnsNull is the match-default-returns-null sin.
type MatchDefaultReturnsNull struct{}

func init() { sins.Register(catalog.Backend, MatchDefaultReturnsNull{}) }

// Definition is what the sin states about itself.
func (MatchDefaultReturnsNull) Definition() sins.Definition {
	return sins.Definition{
		Name:        "match-default-returns-null",
		Skill:       skills.EnumsWithBehaviour{},
		Description: "`match` `default` that returns `null`/`false`/`[]` (or has no body) instead of throwing",
		Rule:        "A `match`/`switch` `default` for an unhandled case must throw, not return `null`/`false`/`[]`.",
		Suggestion:  "`default => throw Unhandled::for($x)`.",
	}
}
