package python

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed flow.intro.md
	flowIntro string
	//go:embed flow.principle.md
	flowPrinciple string
)

// Flow teaches: check preconditions at the top and leave (`return`/`raise`/`continue`), keep the body flat, no `else` after an exit, dispatch instead of an `elif` ladder over one subject.
type Flow struct{}

func init() {
	skill.Register(catalog.Python, Flow{})
}

// Definition is what the skill states about itself.
func (Flow) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/flow",
		Tier:      skill.Mandatory,
		Order:     29,
		Title:     "Python flow — guard at the top, keep the body flat",
		Trigger:   "Shaping a Python function body — a precondition or `None` check at the start of a `def`, an `if` that decides whether the rest of the function runs, an `if`/`elif`/`else` chain, a loop whose whole body sits under one `if`, a block nested three deep, or an `else:` after a branch that already returned or raised. Read this BEFORE writing any of them, and when a Python flow finding points here.",
		Intro:     flowIntro,
		Summary:   "check preconditions at the top and leave (`return`/`raise`/`continue`), keep the body flat, no `else` after an exit, dispatch instead of an `elif` ladder over one subject.",
		Principle: flowPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/guard-clauses-and-flow", Note: "the same discipline on the PHP backend."},
			{Slug: "backend/enums-with-behaviour", Note: "where an `elif` ladder over one subject goes: a closed set with the per-case answer on it."},
		},
	}
}
