package csharp

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

// RoleVocabulary teaches: a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver` and honour the contract — a registry `Get` throws on a miss.
type RoleVocabulary struct{}

func init() {
	skill.Register(catalog.CSharp, RoleVocabulary{})
}

// Definition is what the skill states about itself.
func (RoleVocabulary) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/role-vocabulary",
		Tier:      skill.KeepInMind,
		Order:     46,
		Title:     "C# role vocabulary — a Registry, a Set, a Resolver, and the contract each name promises",
		Trigger:   "Writing a C# class that holds a `Dictionary` of things by key and hands them out, one that collects things to ask whether it holds one, or a chain of `if`s that picks the first handler that matches — or naming a class `*Registry`, `*Set` or `*Resolver`. Read this before you write `public Handler? Get(string key)` on a store, and when a role-vocabulary finding points here.",
		Intro:     roleVocabularyIntro,
		Summary:   "a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver` and honour the contract — a registry `Get` throws on a miss.",
		Principle: roleVocabularyPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/role-vocabulary", Note: "the same roles in PHP, with scaffolded bases."},
			{Slug: "csharp/absence", Note: "a registry `Get` throws on a miss — the same \"a must-exist thing that is missing throws\" rule."},
			{Slug: "csharp/exceptions", Note: "the named exception a registry throws on a miss, built by a static factory."},
		},
	}
}
