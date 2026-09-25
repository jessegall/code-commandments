package python

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

// DependencyDirection teaches: a declared layer may only import the layers it declared it may use — down the stack, never back up, never sideways, and never in a cycle.
type DependencyDirection struct{}

func init() {
	skill.Register(catalog.Python, DependencyDirection{})
}

// Definition is what the skill states about itself.
func (DependencyDirection) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/dependency-direction",
		Tier:      skill.KeepInMind,
		Order:     43,
		Title:     "Python dependency direction — imports point down the stack",
		Trigger:   "Adding an import between two of the project's own Python packages. Read this before `from shop.ui.shared import …` inside `shop.ui.elements`, before an import inside a function added to dodge a circular import, and when a namespace-cycle or namespace-dependency finding points here. A declared layer may only import the layers it said it may use.",
		Intro:     dependencyDirectionIntro,
		Summary:   "a declared layer may only import the layers it declared it may use — down the stack, never back up, never sideways, and never in a cycle.",
		Principle: dependencyDirectionPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/dependency-direction", Note: "the same discipline over PHP namespaces."},
			{Slug: "python/fix-at-the-source", Note: "the arrow that points the wrong way is where the fix belongs."},
		},
	}
}
