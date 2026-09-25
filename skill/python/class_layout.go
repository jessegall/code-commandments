package python

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

// ClassLayout teaches: state at the top — constants, class attributes and fields above `__init__`, methods after.
type ClassLayout struct{}

func init() {
	skill.Register(catalog.Python, ClassLayout{})
}

// Definition is what the skill states about itself.
func (ClassLayout) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/class-layout",
		Tier:      skill.Mandatory,
		Order:     36,
		Title:     "Python class layout — the inventory at the top",
		Trigger:   "Adding a class attribute, a constant, or a dataclass field to a Python class — especially one that already has methods — or placing a nested class, a `ClassVar` or an annotated field. Read this BEFORE you declare state below a `def`, and when a class-layout finding points here.",
		Intro:     classLayoutIntro,
		Summary:   "state at the top — constants, class attributes and fields above `__init__`, methods after.",
		Principle: classLayoutPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/class-layout", Note: "the same discipline over PHP classes."},
			{Slug: "python/value-objects", Note: "a dataclass whose fields are its inventory — read at a glance."},
		},
	}
}
