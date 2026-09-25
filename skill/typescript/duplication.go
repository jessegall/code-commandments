package typescript

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

func init() {
	skill.Register(catalog.TypeScript, Duplication{})
}

//go:embed text/duplication/intro.md
var duplicationIntro string

//go:embed text/duplication/principle.md
var duplicationPrinciple string

// Duplication teaches: a function body written twice becomes one shared function or composable, parameterised by what differs.
type Duplication struct{}

func (Duplication) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "typescript/duplication",
		Tier:      skill.KeepInMind,
		Order:     27,
		Title:     "TypeScript duplication — one behaviour, one home",
		Trigger:   "Copying a function body from one component or module into another — the same `load`/`format`/`submit` written a second time in a different `<script setup>` or `.ts` file, or a near-copy that differs only in the endpoint, the field or the label it uses. Read this BEFORE pasting a function you already wrote somewhere else, and when a `duplicate-function` finding points here. The fix is a shared function or composable that both call, parameterised by whatever actually differs.",
		Intro:     duplicationIntro,
		Summary:   "a function body written twice becomes one shared function or composable, parameterised by what differs.",
		Principle: duplicationPrinciple,
		Languages: []string{"ts", "vue"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: "the same instinct on the server — one decision, made once, where it is born."},
			{Slug: "frontend/vue-components", Note: "the template twin: markup written twice is a component waiting to be extracted."},
		},
	}
}
