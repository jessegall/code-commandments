package python

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed enums.intro.md
	enumsIntro string
	//go:embed enums.principle.md
	enumsPrinciple string
)

// Enums teaches: a closed set of values is an `Enum` or `StrEnum` carrying the per-case knowledge as methods, not string constants compared at every call site.
type Enums struct{}

func init() {
	skill.Register(catalog.Python, Enums{})
}

// Definition is what the skill states about itself.
func (Enums) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/enums",
		Tier:      skill.KeepInMind,
		Order:     33,
		Title:     "Python enums — seal the set, put the knowledge on the case",
		Trigger:   "A fixed set of values in Python — statuses, kinds, modes — written as string literals compared at call sites (`if status == \"paid\"`, `kind in (\"box\", \"pallet\")`), as module constants, or dispatched on with a `match` over strings. Read this BEFORE comparing a value against a literal it is one of a handful of.",
		Intro:     enumsIntro,
		Summary:   "a closed set of values is an `Enum` or `StrEnum` carrying the per-case knowledge as methods, not string constants compared at every call site.",
		Principle: enumsPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/enums-with-behaviour", Note: "the same discipline on the PHP backend."},
			{Slug: "python/flow", Note: "an `elif` ladder over one subject is where a missing enum usually shows first."},
		},
	}
}
