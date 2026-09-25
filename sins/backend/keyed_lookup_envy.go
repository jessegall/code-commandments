package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// KeyedLookupEnvy is the keyed-lookup-envy sin.
type KeyedLookupEnvy struct{}

func init() { sins.Register(catalog.Backend, KeyedLookupEnvy{}) }

// Definition is what the sin states about itself.
func (KeyedLookupEnvy) Definition() sins.Definition {
	return sins.Definition{
		Name:        "keyed-lookup-envy",
		Skill:       skills.TellDontAsk{},
		Description: `Indirect feature envy — a method that uses an owned object's IDENTITY as a key to look up a fact about it through a collaborator`,
		Rule:        `Ask the object directly; don't use its identity as a key to look its own fact up through a collaborator.`,
		Suggestion:  "Move the lookup onto the object that owns the identity.",
	}
}
