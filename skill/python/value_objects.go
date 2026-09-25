package python

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed value_objects.intro.md
	valueObjectsIntro string
	//go:embed value_objects.principle.md
	valueObjectsPrinciple string
)

// ValueObjects teaches: give related data a type — a frozen dataclass — instead of a dict with string keys passed around, or values that always travel together.
type ValueObjects struct{}

func init() {
	skill.Register(catalog.Python, ValueObjects{})
}

// Definition is what the skill states about itself.
func (ValueObjects) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/value-objects",
		Tier:      skill.Mandatory,
		Order:     32,
		Title:     "Python value objects — give related data a type",
		Trigger:   "Passing or returning a `dict` whose string keys are a fixed record (`{\"sku\": …, \"quantity\": …}`), reading `row[\"field\"]` or `payload.get(\"field\")` on data your own code built, or adding a third parameter that always travels with two others. Read this BEFORE you shape data as a dict or grow a signature — the answer is usually a frozen dataclass.",
		Intro:     valueObjectsIntro,
		Summary:   "give related data a type — a frozen dataclass — instead of a dict with string keys passed around, or values that always travel together.",
		Principle: valueObjectsPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/value-objects", Note: "the same discipline on the PHP backend."},
			{Slug: "python/absence", Note: "a field that is always there is typed as such, not read with `.get(...) or \"\"`."},
		},
	}
}
