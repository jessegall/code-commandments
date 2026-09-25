package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// NullableRegistryLookup is the nullable-registry-lookup sin.
type NullableRegistryLookup struct{}

func init() { sins.Register(catalog.Backend, NullableRegistryLookup{}) }

// Definition is what the sin states about itself.
func (NullableRegistryLookup) Definition() sins.Definition {
	return sins.Definition{
		Name:        "nullable-registry-lookup",
		Skill:       skills.RoleVocabulary{},
		Description: "A keyed-store `get()` that returns `null` on a miss (should resolve-or-throw)",
		Rule:        "A keyed store's `get()` resolves-or-throws on a miss; don't return `null`.",
		Suggestion:  "`get()` returns-or-throws a named `…NotFound::forKey($key)`.",
	}
}
