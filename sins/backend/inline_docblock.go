package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// InlineDocblock is the inline-docblock sin.
type InlineDocblock struct{}

func init() { sins.Register(catalog.Backend, InlineDocblock{}) }

// Definition is what the sin states about itself.
func (InlineDocblock) Definition() sins.Definition {
	return sins.Definition{
		Name:        "inline-docblock",
		Skill:       skills.Documentation{},
		Description: `A docblock whose delimiter shares a line with its text — a one-liner, or a block that opens or closes next to content`,
		Rule:        `Write a docblock as a block: the opening delimiter on its own line, one star per line of content, the closing delimiter on its own line.`,
		Suggestion:  "expand it — `repent` does this for you",
	}
}
