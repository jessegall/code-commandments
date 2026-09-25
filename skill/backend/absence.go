package backend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed absence.intro.md
	absenceIntro string
	//go:embed absence.principle.md
	absencePrinciple string
)

// Absence teaches: modelling a value that might be missing (`?T`, `Option`, `null`, empty, Null Object, throw).
type Absence struct{}

func init() {
	skill.Register(catalog.Backend, Absence{})
}

// Definition is what the skill states about itself.
func (Absence) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/absence",
		Tier:      skill.Mandatory,
		Order:     7,
		Title:     "Absence — model \"might not be there\" honestly",
		Trigger:   "Decide how a value that might not be there is modelled — throw vs Option vs an empty collection vs a Null Object vs a plain nullable. Read this FIRST whenever you are about to write a `?T` / `T | null` return or property, `return null`, an `Option`, a `?->` / `=== null` / `?? default`; whenever you are choosing between returning null and returning something empty for \"nothing matched\"; whenever an optional collaborator or callback is normalised with `??` in a constructor or body; or whenever you are unsure if something \"can be missing\". Answers when it is OK to return null and when it is a bug.",
		Intro:     absenceIntro,
		Summary:   "modelling a value that might be missing (`?T`, `Option`, `null`, empty, Null Object, throw).",
		Principle: absencePrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: "The parent move is fix-at-the-source: decide absence at the producer, not at the callers."},
		},
	}
}
