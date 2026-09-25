package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

// GuardClausesAndFlow is the backend/guard-clauses-and-flow discipline.
type GuardClausesAndFlow struct{}

func init() { skill.Register(catalog.Backend, GuardClausesAndFlow{}) }

// Definition is what the skill states about itself.
func (GuardClausesAndFlow) Definition() skill.Definition {
	return skill.Definition{
		Slug:    "backend/guard-clauses-and-flow",
		Tier:    skill.Mandatory,
		Order:   2,
		Title:   "Guard clauses & flow — check at the top, then go straight",
		Trigger: `How a method body is shaped — validate preconditions at the TOP with early return/throw, keep the body flat (no if/elseif/else ladders, no deep nesting), and run the happy path last. NEVER bury a check inline (` + "`" + `($x ?? throw …)->y()` + "`" + `) or in a nested branch. Read this BEFORE writing a method body, a precondition/null check, an ` + "`" + `if` + "`" + `, or anything that throws or returns early.`,
		Intro: `Decide the unhappy paths first, at the door, and leave. What's left is the happy path, flat and
unindented. A method should read top-to-bottom: *here's what would stop us → here's the work.*`,
		Summary: `validate preconditions at the TOP (early return/throw), flat body, happy path last; never bury a check inline.`,
		Principle: `Every precondition a method depends on — a value that must be present, a state that must hold — is checked
**at the top** and short-circuits with a ` + "`" + `return` + "`" + ` or a ` + "`" + `throw` + "`" + `. By the time control reaches the real work,
everything it needs is guaranteed, so the work runs at the base indentation level with no ` + "`" + `else` + "`" + ` and no
nesting. The shape itself documents the contract.

The opposite — burying a check inside an expression, or wrapping the happy path in ` + "`" + `if (ok) { … }` + "`" + ` — hides
the contract and pushes the body rightward until it's unreadable.

### When to use this skill

Reach for this the moment you are about to write:

- a **precondition / null / state check** at the start of a method;
- an ` + "`" + `if` + "`" + ` that decides whether the rest of the method runs;
- an ` + "`" + `if/elseif/else` + "`" + ` chain, or a branch nested two-deep;
- anything that **throws or returns early**.`,
		Languages: []string{"php"},
		Related: []skill.Related{
			{Slug: "backend/exceptions", Reason: "*how* a guard throws (named factory, never a message string)."},
			{Slug: "backend/absence", Reason: "*whether* a missing value is a guard-and-throw at all, vs Option / empty / default."},
			{Slug: "backend/fix-at-the-source", Reason: "if every caller re-guards the same value, the guard belongs upstream where the value is born."},
		},
	}
}
