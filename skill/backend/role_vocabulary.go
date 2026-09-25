package backend

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

// RoleVocabulary teaches: a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver`, extend the base, honour the contract.
type RoleVocabulary struct{}

func init() {
	skill.Register(catalog.Backend, RoleVocabulary{})
}

// Definition is what the skill states about itself.
func (RoleVocabulary) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/role-vocabulary",
		Tier:      skill.KeepInMind,
		Order:     10,
		Title:     "Role vocabulary — the name is the contract",
		Trigger:   "The three recurring structural roles — Registry (keyed store), Set (membership), Resolver (first-match dispatch) — each with a name and a contract. If a class IS one of these shapes, name it `*Registry`/`*Set`/`*Resolver` and extend the scaffolded base; if it's NAMED one, it must behave like one. Read this BEFORE you hand-roll a keyed store / lookup table, an add-and-iterate collection, or an if/elseif chain that picks the first matching handler — or name a class `*Registry`/`*Set`/`*Resolver`.",
		Intro:     roleVocabularyIntro,
		Summary:   "a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver`, extend the base, honour the contract.",
		Principle: roleVocabularyPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/absence", Note: "a registry `get()` is resolve-or-throw, not an Option; that's the same \"missing must-exist thing throws\" rule."},
			{Slug: "backend/exceptions", Note: "the named exception a registry throws on a miss (`RegistryEntryNotFoundException::forKey($key)`)."},
			{Slug: "backend/value-objects", Note: "these roles are typed structures; reach for one instead of threading a raw `array` keyed store around."},
		},
	}
}
