package python

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed duplication.intro.md
	duplicationIntro string
	//go:embed duplication.principle.md
	duplicationPrinciple string
)

// Duplication teaches: a function body written twice becomes one shared function, parameterised by what differs.
type Duplication struct{}

func init() {
	skill.Register(catalog.Python, Duplication{})
}

// Definition is what the skill states about itself.
func (Duplication) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/duplication",
		Tier:      skill.KeepInMind,
		Order:     28,
		Title:     "Python duplication — one behaviour, one home",
		Trigger:   "Copying a function or method body from one Python module into another — the same `load`/`render`/`validate` written a second time under another name, or a near-copy that differs only in the path, the key or the message it uses. Read this BEFORE pasting a `def` you already wrote somewhere else, and when a `duplicate-python-function` finding points here. The fix is one function both call, parameterised by whatever actually differs.",
		Intro:     duplicationIntro,
		Summary:   "a function body written twice becomes one shared function, parameterised by what differs.",
		Principle: duplicationPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: "the root instinct — one decision, made once, where it is born."},
			{Slug: "typescript/duplication", Note: "the same discipline over TypeScript modules and Vue components."},
		},
	}
}
