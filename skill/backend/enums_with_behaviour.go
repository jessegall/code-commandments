package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

// EnumsWithBehaviour is the backend/enums-with-behaviour discipline.
type EnumsWithBehaviour struct{}

func init() { skill.Register(catalog.Backend, EnumsWithBehaviour{}) }

// Definition is what the skill states about itself.
func (EnumsWithBehaviour) Definition() skill.Definition {
	return skill.Definition{
		Slug:    "backend/enums-with-behaviour",
		Tier:    skill.KeepInMind,
		Order:   9,
		Title:   "Enums with behaviour — seal the set, put the logic on the type",
		Trigger: `How a closed set of values is modelled — a native backed enum (never raw strings or a const class), with the knowledge keyed off its cases living ON the enum as methods, not re-inlined as a ` + "`" + `match` + "`" + `/` + "`" + `switch` + "`" + ` at every call site. Read this BEFORE you write a fixed set of string/int values, a ` + "`" + `match` + "`" + `/` + "`" + `switch` + "`" + ` over an enum (or over strings that mirror one), a ` + "`" + `const` + "`" + ` class of scalars, or a string field whose values are a closed set.`,
		Intro: `A closed set of values is a **type**, and what you *do* per value belongs **on** that type. The smell is
a set expressed as loose strings, or an enum whose cases are matched over and over at the call sites
instead of answering for themselves.`,
		Summary: `a closed set of values: seal it as a native backed enum, put the per-case logic on the enum (not a ` + "`" + `match` + "`" + ` at every call site).`,
		Principle: `Two moves, always together:

1. **Seal the set.** A fixed range of values — statuses, kinds, modes — is a **native backed enum**. Not
   raw string literals scattered across comparisons, not a ` + "`" + `const` + "`" + ` class of scalars, not a ` + "`" + `string` + "`" + ` field
   that "happens to" hold one of five values.
2. **Put the behaviour on the case.** The knowledge keyed off the set — a per-case value, a per-case
   decision — lives as a **method on the enum**, computed once with an exhaustive ` + "`" + `match` + "`" + `. A ` + "`" + `match` + "`" + ` /
   ` + "`" + `switch` + "`" + ` over an enum *at a call site* is that method, homeless.

Sealing the set without moving the behaviour just relocates the ` + "`" + `match` + "`" + ` statements; the win is the enum
*answering for itself*.

### When to use this skill

Reach for this the moment you write:

- a **fixed set of string/int values** used as discrete choices (compared, ` + "`" + `in_array` + "`" + `'d, switched on);
- a **` + "`" + `match` + "`" + ` / ` + "`" + `switch` + "`" + ` over an enum** — especially the *same* enum in more than one place;
- a ` + "`" + `match` + "`" + ` / ` + "`" + `switch` + "`" + ` over **strings that mirror an enum's cases** (` + "`" + `'pending'` + "`" + `, ` + "`" + `'done'` + "`" + ` …);
- a **` + "`" + `const` + "`" + ` class** of scalar values used as a closed set;
- a **` + "`" + `string` + "`" + `/` + "`" + `int` + "`" + ` property** whose value space is actually closed.`,
		Languages: []string{"php"},
		Related: []skill.Related{
			{Slug: "backend/value-objects", Reason: `an enum is the closed-set member of "give data a type"; reach for it when the type's values are a fixed set.`},
			{Slug: "backend/absence", Reason: "a missing/unhandled case is a throw, not a silent `default`."},
			{Slug: "backend/exceptions", Reason: "a missing/unhandled case is a throw, not a silent `default`."},
			{Slug: "backend/fix-at-the-source", Reason: `seal the set where the value is born (a typed enum field) so downstream code never re-parses a string.`},
		},
	}
}
