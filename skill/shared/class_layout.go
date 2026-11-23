package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed class_layout.md
var classLayoutBody string

// ClassLayout teaches the fixed order of a class in every language: everything it holds at the top, then what it does.
type ClassLayout struct{}

func init() {
	skill.Register(catalog.Shared, ClassLayout{})
}

func (ClassLayout) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/class-layout",
		Tier:      skill.Mandatory,
		Order:     7,
		Title:     "Class layout — state first, then behaviour",
		Trigger:   "Adding a constant, a field, a property, a computed property, a trait use or an enum case to a class, especially one that already has methods. Read this BEFORE you declare state below a method or next to the method that uses it, and when a class-layout finding points here.",
		Summary:   "declare what a class has above what it does, in one fixed order: trait uses, cases, constants, static fields, fields from public to private, computed properties, then the constructor and the methods.",
		Body:      classLayoutBody,
		Formerly:  []string{"php/class-layout", "csharp/class-layout", "python/class-layout"},
		Languages: languages,
	}
}
