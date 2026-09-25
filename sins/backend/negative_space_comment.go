package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// NegativeSpaceComment is the negative-space-comment sin.
type NegativeSpaceComment struct{}

func init() { sins.Register(catalog.Backend, NegativeSpaceComment{}) }

// Definition is what the sin states about itself.
func (NegativeSpaceComment) Definition() sins.Definition {
	return sins.Definition{
		Name:        "negative-space-comment",
		Skill:       skills.Documentation{},
		Description: `A comment defending the code against a strawman ("not random", "no magic", "not a coincidence", "not dead code")`,
		Rule:        `State what the code IS, affirmatively — a comment that defends it against a strawman (that it is "not random", "no magic", "not a typo") is negative space; make the code self-evident and delete the comment.`,
	}
}
