package python

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed type_honesty.intro.md
	typeHonestyIntro string
	//go:embed type_honesty.principle.md
	typeHonestyPrinciple string
)

// TypeHonesty teaches: a type must not lie: don't fake optionality with `| None` a value never is, or keep per-call scratch state on `self`.
type TypeHonesty struct{}

func init() {
	skill.Register(catalog.Python, TypeHonesty{})
}

// Definition is what the skill states about itself.
func (TypeHonesty) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/type-honesty",
		Tier:      skill.Mandatory,
		Order:     35,
		Title:     "Python type honesty — the annotation must not lie",
		Trigger:   "Annotating an attribute or a parameter `X | None` that the design always has set, reading it back with `x.y if x else …` or `getattr(x, \"y\", default)`, filling a required `str` field with `\"\"` to satisfy a signature, saving `self.x` to a local and restoring it later, or a `@property` that returns a constant. Read this BEFORE you widen a type to make something pass, and when a type-honesty finding points here.",
		Intro:     typeHonestyIntro,
		Summary:   "a type must not lie: don't fake optionality with `| None` a value never is, or keep per-call scratch state on `self`.",
		Principle: typeHonestyPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/type-honesty", Note: "the same discipline over PHP."},
			{Slug: "python/absence", Note: "the complement: absence models a genuine maybe-missing; this kills a fake one."},
			{Slug: "python/fix-at-the-source", Note: "make the type certain where the value is born, not defended at every read."},
		},
	}
}
