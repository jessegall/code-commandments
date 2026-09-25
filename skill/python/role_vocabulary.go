package python

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed role_vocabulary.intro.md
	roleVocabularyIntro string
	//go:embed role_vocabulary.principle.md
	roleVocabularyPrinciple string
)

// RoleVocabulary teaches: a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver` and honour the contract — a registry `get` raises on a miss.
type RoleVocabulary struct{}

func init() {
	skill.Register(catalog.Python, RoleVocabulary{})
}

// Definition is what the skill states about itself.
func (RoleVocabulary) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/role-vocabulary",
		Tier:      skill.KeepInMind,
		Order:     45,
		Title:     "Python role vocabulary — a Registry, a Set, a Resolver, and the contract each name promises",
		Trigger:   "Writing a Python class that holds a dict of things by key and hands them out, one that collects things to ask whether it holds one, or a chain of `if`s that picks the first handler that matches — or naming a class `*Registry`, `*Set` or `*Resolver`. Read this before you write `def get(self, key) -> X | None` on a store, and when a role-vocabulary finding points here.",
		Intro:     roleVocabularyIntro,
		Summary:   "a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver` and honour the contract — a registry `get` raises on a miss.",
		Principle: roleVocabularyPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/role-vocabulary", Note: "the same roles in PHP, with scaffolded bases."},
			{Slug: "python/absence", Note: "a registry `get` raises on a miss — the same \"a must-exist thing that is missing raises\" rule."},
			{Slug: "python/exceptions", Note: "the named exception a registry raises on a miss, built by a classmethod factory."},
		},
	}
}
