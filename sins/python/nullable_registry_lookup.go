package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// NullableRegistryLookup is a keyed store handing back `None` for a key it lacks — `return self._handlers.get(kind)` — so every caller decides what a miss means.
type NullableRegistryLookup struct{}

func init() {
	sins.Register(catalog.Python, NullableRegistryLookup{})
}

// Definition is what the sin states about itself.
func (NullableRegistryLookup) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-nullable-registry-lookup",
		Skill:       skills.RoleVocabulary{},
		Description: "a keyed store handing back `None` for a key it lacks — `return self._handlers.get(kind)` — so every caller decides what a miss means",
		Rule:        "A store's lookup returns the item or raises a named exception; where a miss is genuinely expected, callers ask `key in store` first.",
		Suggestion:  "Index the dict and turn the `KeyError` into a named exception (`raise UnknownHandler.for_kind(kind) from missing`), and give the class a `__contains__` for the callers that expect misses.",
	}
}
