package backend

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

// TypeHonesty teaches: a type must not lie: don't fake optionality — a `?T` the design always has set, then defended with `?->`/`?? <fake>` or stashed as save/restore scratch state. Make the type certain (pass it, hold it non-nullable, a per-call value object). The complement of `absence`.
type TypeHonesty struct{}

func init() {
	skill.Register(catalog.Backend, TypeHonesty{})
}

// Definition is what the skill states about itself.
func (TypeHonesty) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/type-honesty",
		Tier:      skill.Mandatory,
		Order:     12,
		Title:     "Type honesty — the type must not lie",
		Trigger:   "A type must tell the truth about the value. Don't fake optionality — a `?T` / nullable that the design always has set, which the code then immediately defends against (`?->`, `?? <fake>`, null-checks) or stashes as mutable scratch state and restores. The defence is the tell that the type is lying. Make the type carry the certainty: pass the value as a parameter, hold it non-nullable, or wrap per-call context in a value object. Read this BEFORE you add a nullable field set later in a method, or reach for `$this->scratch?->… ?? false`.",
		Intro:     typeHonestyIntro,
		Summary:   "a type must not lie: don't fake optionality — a `?T` the design always has set, then defended with `?->`/`?? <fake>` or stashed as save/restore scratch state. Make the type certain (pass it, hold it non-nullable, a per-call value object). The complement of `absence`.",
		Principle: typeHonestyPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/absence", Note: "the complement: absence models a genuine maybe-missing; this kills a FAKE one."},
			{Slug: "backend/fix-at-the-source", Note: "make the type certain where the value is born, not defended at every read."},
		},
	}
}
