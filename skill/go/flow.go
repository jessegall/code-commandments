// Package go holds the Go engine's skills: what good Go looks like, one skill per subject, which a finding
// points an agent at.
package golang

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

// Flow teaches: guard at the top and leave, keep the body flat, no `else` after a branch that already left, and
// a switch over a defined type rather than a ladder of comparisons on one subject.
type Flow struct{}

func init() {
	skill.Register(catalog.Go, Flow{})
}

// Definition is what the skill states about itself.
func (Flow) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "go/flow",
		Tier:      skill.Mandatory,
		Order:     41,
		Title:     "Go flow — guard at the top, keep the body flat",
		Trigger:   "Shaping a Go function body: an error check at the top, an `if` that decides whether the rest of the function runs, an if/else-if chain over one value, a `for` whose whole body sits under one `if`, a block four choices deep, or an `else` after a branch that already returned. Read this BEFORE writing any of them, and when a Go flow finding points here.",
		Intro:     flowIntro,
		Summary:   "guard at the top and leave, keep the body flat, no `else` after a branch that already left, and a switch over a defined type rather than a ladder of comparisons on one subject.",
		Principle: flowPrinciple,
		Languages: []string{"go"},
		Related: []skill.Relation{
			{Slug: "python/flow", Note: "the same discipline in Python, where the indentation is the cost."},
			{Slug: "php/guard-clauses-and-flow", Note: "the same discipline on the PHP backend."},
		},
	}
}
