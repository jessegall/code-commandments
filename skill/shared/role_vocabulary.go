package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed role_vocabulary.md
var roleVocabularyBody string

// RoleVocabulary teaches the three structural roles every codebase repeats, in every language: a registry, a set and a resolver, each a name with a contract.
type RoleVocabulary struct{}

func init() {
	skill.Register(catalog.Shared, RoleVocabulary{})
}

func (RoleVocabulary) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/role-vocabulary",
		Tier:      skill.KeepInMind,
		Order:     10,
		Title:     "Role vocabulary — the name is the contract",
		Trigger:   "Writing a class that keeps items under a key (a lookup table, a store with `register` and `get`), an add-and-iterate collection, or an if/else-if chain that picks the first matching handler, or naming a class `*Registry`, `*Set` or `*Resolver`. Read this BEFORE you hand-roll one, and when a role-vocabulary finding points here.",
		Summary:   "a keyed store is a Registry, a membership collection a Set, a first-match dispatch a Resolver: name the class for its role, build it on the one base, and keep the contract the name promises.",
		Body:      roleVocabularyBody,
		Formerly:  []string{"php/role-vocabulary", "csharp/role-vocabulary", "python/role-vocabulary"},
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "shared/absence", Note: "why a registry's `get` gives the item or fails by name."},
			{Slug: "shared/duplication", Note: "the plumbing a base class writes once."},
		},
	}
}
