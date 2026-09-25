package csharp

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

// ClassLayout teaches: state at the top — constants, fields and stored properties above the constructor, methods after.
type ClassLayout struct{}

func init() {
	skill.Register(catalog.CSharp, ClassLayout{})
}

// Definition is what the skill states about itself.
func (ClassLayout) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/class-layout",
		Tier:      skill.Mandatory,
		Order:     37,
		Title:     "C# class layout — what the object holds, first",
		Trigger:   "Adding a field, a constant or an auto-property to a C# class, record or struct — especially one that already has methods — or deciding where a new member goes. Read this BEFORE you declare state below a method, and when a class-layout finding points here.",
		Intro:     classLayoutIntro,
		Summary:   "state at the top — constants, fields and stored properties above the constructor, methods after.",
		Principle: classLayoutPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/class-layout", Note: "the same discipline in PHP."},
			{Slug: "csharp/value-objects", Note: "a record whose members are its list of state — read at a glance."},
		},
	}
}
