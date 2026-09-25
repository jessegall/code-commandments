package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// StackedDocblock is the stacked-docblock sin.
type StackedDocblock struct{}

func init() { sins.Register(catalog.Backend, StackedDocblock{}) }

// Definition is what the sin states about itself.
func (StackedDocblock) Definition() sins.Definition {
	return sins.Definition{
		Name:        "stacked-docblock",
		Skill:       skills.Documentation{},
		Description: `Two or more docblocks stacked on one declaration — PHP reads only the last, so the ones above it are documentation nobody sees`,
		Rule:        `One declaration carries ONE docblock — merge a stack into a single block, because the language hands only the last one to a reader's tooling.`,
		Suggestion:  "merge them into one block — `repent` does this for you",
	}
}
