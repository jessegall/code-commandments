package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed dependencies.md
var dependenciesBody string

// Dependencies teaches which way dependencies point in every language: downwards, through contracts at a module's boundary, never in a circle.
type Dependencies struct{}

func init() {
	skill.Register(catalog.Shared, Dependencies{})
}

func (Dependencies) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/dependencies",
		Tier:      skill.KeepInMind,
		Order:     17,
		Title:     "Dependencies — point one way, through contracts at the boundary",
		Trigger:   "Adding an import, a type hint, a `new` or a constructor parameter that crosses a module boundary (a namespace, a package, a folder), moving a class between modules, deciding where a new class belongs, declaring the project's layers, or adding an interface. Read this BEFORE you do, and when a dependencies, namespace-cycle or namespace-dependency finding points here.",
		Summary:   "dependencies point down the declared layers and never in a circle; across a module boundary depend on a contract, inside one do not invent an interface nobody needs or repeat one that exists.",
		Body:      dependenciesBody,
		Formerly:  []string{"php/dependency-direction", "csharp/dependency-direction", "python/dependency-direction"},
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "shared/tell-dont-ask", Note: "what to do instead of reaching into another module's objects."},
		},
	}
}
