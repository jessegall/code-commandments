package backend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed class_layout.intro.md
	classLayoutIntro string
	//go:embed class_layout.principle.md
	classLayoutPrinciple string
)

// ClassLayout teaches: state at the top — traits, constants, properties and hooks above the constructor, methods after.
type ClassLayout struct{}

func init() {
	skill.Register(catalog.Backend, ClassLayout{})
}

// Definition is what the skill states about itself.
func (ClassLayout) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/class-layout",
		Tier:      skill.Mandatory,
		Order:     7,
		Title:     "Class layout — state first, then behaviour",
		Trigger:   `Where a declaration goes in a class: every trait use, constant, property and property hook stands at the TOP, above the constructor — never between two methods, never appended at the bottom. Read this when you add a constant or a field to an existing class, or when you are about to write a declaration below a method.`,
		Intro:     classLayoutIntro,
		Summary:   "state at the top — traits, constants, properties and hooks above the constructor, methods after.",
		Principle: classLayoutPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/behaviour-per-method", Note: `a crowded head of class is usually a class doing several jobs; split the behaviour and the state follows.`},
			{Slug: "backend/documentation", Note: "a structural section divider is fine, but it never justifies state living below the methods."},
		},
	}
}
