package backend

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

// DependencyDirection teaches: a declared layer may only reference the layers it declared it may use — down the stack, never back up, never sideways; the direction is enforced from the project's own layer declaration.
type DependencyDirection struct{}

func init() {
	skill.Register(catalog.Backend, DependencyDirection{})
}

// Definition is what the skill states about itself.
func (DependencyDirection) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/dependency-direction",
		Tier:      skill.KeepInMind,
		Order:     17,
		Title:     "Dependency direction — a layer may only reach DOWN",
		Trigger:   "Which layer may know about which. When a project declares its layers (under `configure` in `.commandments/config.json`: `{\"layer\": [\"App\\\\Ui\\\\Shared\", [\"App\\\\Ui\\\\Elements\"]]}`), every reference OUT of a declared layer must point at a layer it is allowed to use — down the stack, never back up and never sideways. Read this before adding an import, a type hint, a `new`, or a static call that crosses a namespace boundary, before moving a class between namespaces, and when deciding where a new class belongs.",
		Intro:     dependencyDirectionIntro,
		Summary:   `a declared layer may only reference the layers it declared it may use — down the stack, never back up, never sideways; the direction is enforced from the project's own layer declaration.`,
		Principle: dependencyDirectionPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/value-objects", Note: "the value a low layer needs instead of the high-level class it reached for."},
			{Slug: "backend/tell-dont-ask", Note: "reaching up for an object's data is the same instinct, one scale down."},
		},
	}
}
