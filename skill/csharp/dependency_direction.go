package csharp

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed dependency_direction.intro.md
	dependencyDirectionIntro string
	//go:embed dependency_direction.principle.md
	dependencyDirectionPrinciple string
)

// DependencyDirection teaches: a declared layer may only use the layers it declared it may use — down the stack, never back up, never sideways, and never in a cycle.
type DependencyDirection struct{}

func init() {
	skill.Register(catalog.CSharp, DependencyDirection{})
}

// Definition is what the skill states about itself.
func (DependencyDirection) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/dependency-direction",
		Tier:      skill.KeepInMind,
		Order:     44,
		Title:     "C# dependency direction — references point down the stack",
		Trigger:   "Using a type from another of the project's own namespaces. Read this before `Shop.Domain` code reaches for a type in `Shop.Web`, before a new `using` between two of your namespaces, and when a namespace-cycle or namespace-dependency finding points here. A declared layer may only use the layers it said it may use.",
		Intro:     dependencyDirectionIntro,
		Summary:   "a declared layer may only use the layers it declared it may use — down the stack, never back up, never sideways, and never in a cycle.",
		Principle: dependencyDirectionPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/dependency-direction", Note: "the same discipline over PHP namespaces."},
			{Slug: "csharp/fix-at-the-source", Note: "the arrow that points the wrong way is where the fix belongs."},
		},
	}
}
