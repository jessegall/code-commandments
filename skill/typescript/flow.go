package typescript

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

func init() {
	skill.Register(catalog.TypeScript, Flow{})
}

//go:embed text/flow/intro.md
var flowIntro string

//go:embed text/flow/principle.md
var flowPrinciple string

// Flow teaches: turn away what cannot go on at the top, keep the body flat, and never change a collection while walking it.
type Flow struct{}

func (Flow) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "typescript/flow",
		Tier:      skill.KeepInMind,
		Order:     28,
		Title:     "TypeScript flow — turn away at the top, then go straight",
		Trigger:   "Shaping a function body in a `.ts` file or a `<script setup>`: a check that decides whether the rest of it runs, an `if`/`else if` chain, a block nested three deep, an `else` after a branch that already returned or threw, or a loop that pushes to, splices out of or deletes from the very collection it walks. Read this BEFORE writing any of them, and when a TypeScript flow finding points here.",
		Intro:     flowIntro,
		Summary:   "turn away what cannot go on at the top, keep the body flat, and never change a collection while walking it.",
		Principle: flowPrinciple,
		Languages: []string{"ts", "vue"},
		Related: []skill.Relation{
			{Slug: "vue/vue-control-flow", Note: "the same shape in a template: a branch that belongs in the script, not in the markup."},
			{Slug: "typescript/absence", Note: "WHETHER a value can be missing at all, which decides if the guard should exist."},
		},
	}
}
