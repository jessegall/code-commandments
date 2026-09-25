package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// KeyedLookupEnvy is a method that uses an object's key to fetch a fact about it through a collaborator — `self.registry.get(node.key).reserved` — treating the object as a key into its own data.
type KeyedLookupEnvy struct{}

func init() {
	sins.Register(catalog.Python, KeyedLookupEnvy{})
}

// Definition is what the sin states about itself.
func (KeyedLookupEnvy) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-keyed-lookup-envy",
		Skill:       skills.TellDontAsk{},
		Description: "a method that uses an object's key to fetch a fact about it through a collaborator — `self.registry.get(node.key).reserved` — treating the object as a key into its own data",
		Rule:        "Put the fact on the object it is about and ask it (`node.reserved_names()`), instead of looking it up from outside by the object's key.",
		Suggestion:  "Give the object the method, holding what it needs to answer, and call it where this method was called.",
	}
}
