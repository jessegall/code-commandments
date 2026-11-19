// Package shared holds the skills that teach the rules every language judges the same way, published to every project whatever languages it holds.
package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed flow.intro.md
	flowIntro string
	//go:embed flow.principle.md
	flowPrinciple string
)

// Flow teaches the shape of a function body in every language: guard at the top and leave, keep the body flat, decide once per expression, and write each statement where it runs.
type Flow struct{}

func init() {
	skill.Register(catalog.Shared, Flow{})
}

func (Flow) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/flow",
		Tier:      skill.Mandatory,
		Order:     1,
		Title:     "Flow — guard at the top, keep the body flat",
		Trigger:   "Shaping a function body in any language: an `if` that decides whether the rest runs, an `else` after a branch that already returned, a loop whose whole body sits under one `if`, an if/else-if chain over one value, a ternary inside a ternary, a ternary or `&&` used as a statement, a statement every branch repeats, or code after a `return`. Read this BEFORE writing any of them, and when a flow finding points here.",
		Intro:     flowIntro,
		Summary:   "guard at the top and leave, no `else` after a branch that left, a `continue` guard instead of a loop wrapped in an `if`, a dispatch instead of a ladder over one subject, one decision per conditional expression, an `if` for an action, a shared last statement written once after the branches, and no code after the block has left.",
		Principle: flowPrinciple,
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "php/guard-clauses-and-flow", Note: "what is particular to PHP's flow."},
			{Slug: "python/flow", Note: "what is particular to Python's flow, where the indentation is the cost."},
			{Slug: "typescript/flow", Note: "what is particular to TypeScript's flow."},
			{Slug: "csharp/flow", Note: "what is particular to C#'s flow."},
			{Slug: "go/flow", Note: "what is particular to Go's flow."},
		},
	}
}
