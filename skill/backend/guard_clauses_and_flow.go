package backend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed guard_clauses_and_flow.intro.md
	guardClausesAndFlowIntro string
	//go:embed guard_clauses_and_flow.principle.md
	guardClausesAndFlowPrinciple string
)

// GuardClausesAndFlow teaches: validate preconditions at the TOP (early return/throw), flat body, happy path last; never bury a check inline.
type GuardClausesAndFlow struct{}

func init() {
	skill.Register(catalog.Backend, GuardClausesAndFlow{})
}

// Definition is what the skill states about itself.
func (GuardClausesAndFlow) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/guard-clauses-and-flow",
		Tier:      skill.Mandatory,
		Order:     2,
		Title:     "Guard clauses & flow — check at the top, then go straight",
		Trigger:   "How a method body is shaped — validate preconditions at the TOP with early return/throw, keep the body flat (no if/elseif/else ladders, no deep nesting), and run the happy path last. NEVER bury a check inline (`($x ?? throw …)->y()`) or in a nested branch. Read this BEFORE writing a method body, a precondition/null check, an `if`, or anything that throws or returns early.",
		Intro:     guardClausesAndFlowIntro,
		Summary:   `validate preconditions at the TOP (early return/throw), flat body, happy path last; never bury a check inline.`,
		Principle: guardClausesAndFlowPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/exceptions", Note: "*how* a guard throws (named factory, never a message string)."},
			{Slug: "backend/absence", Note: "*whether* a missing value is a guard-and-throw at all, vs Option / empty / default."},
			{Slug: "backend/fix-at-the-source", Note: "if every caller re-guards the same value, the guard belongs upstream where the value is born."},
		},
	}
}
