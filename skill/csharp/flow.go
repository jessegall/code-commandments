package csharp

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

// Flow teaches: check preconditions at the top and leave (`return`/`throw`/`continue`), keep the body flat, no `else` after an exit, a `switch` expression or polymorphism instead of an `else if` ladder over one subject.
type Flow struct{}

func init() {
	skill.Register(catalog.CSharp, Flow{})
}

// Definition is what the skill states about itself.
func (Flow) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/flow",
		Tier:      skill.Mandatory,
		Order:     30,
		Title:     "C# flow — guard at the top, keep the body flat",
		Trigger:   "Shaping a C# method body — a null or precondition check at the start of a method, an `if` that decides whether the rest of the method runs, an `if`/`else if` chain, a loop whose whole body sits under one `if`, a block nested three deep, or an `else` after a branch that already returned or threw. Read this BEFORE writing any of them, and when a C# flow finding points here.",
		Intro:     flowIntro,
		Summary:   "check preconditions at the top and leave (`return`/`throw`/`continue`), keep the body flat, no `else` after an exit, a `switch` expression or polymorphism instead of an `else if` ladder over one subject.",
		Principle: flowPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/guard-clauses-and-flow", Note: "the same discipline on the PHP backend."},
			{Slug: "python/flow", Note: "the same discipline in Python."},
		},
	}
}
