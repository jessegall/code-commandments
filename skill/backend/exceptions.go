package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

// Exceptions is the backend/exceptions discipline.
type Exceptions struct{}

func init() { skill.Register(catalog.Backend, Exceptions{}) }

// Definition is what the skill states about itself.
func (Exceptions) Definition() skill.Definition {
	return skill.Definition{
		Slug:    "backend/exceptions",
		Tier:    skill.KeepInMind,
		Order:   8,
		Title:   "Exceptions — fail hard, fix once",
		Trigger: `How to fail — throw NAMED exceptions via static factories (` + "`" + `Thing::for($x)` + "`" + `), never a message string at the throw site, and never swallow a failure into null/false/[]/Option::none(). Read this FIRST whenever you write a ` + "`" + `throw` + "`" + `, a ` + "`" + `try` + "`" + `/` + "`" + `catch` + "`" + `, an exception class, or are deciding what to do when something goes wrong. Fail hard and named, at the source.`,
		Intro: `**Fail hard, fix once** beats *fail gracefully, debug forever.* A loud, named, contextual failure is a
five-minute fix. A swallowed one is a silent wrong result you chase for a week.`,
		Summary: "throwing or catching: named `::for()` factory exceptions, never swallow a failure.",
		Principle: `A failure is information. The instant it happens, it knows the most it will ever know — *what* broke and
*with what values*. Throw that knowledge **loudly, by type, at the source**. Every line you put between
the failure and its surfacing — a ` + "`" + `catch` + "`" + ` that returns null, a default that papers over it, a bare
` + "`" + `Exception("...")` + "`" + ` — destroys information and moves the eventual debugging session further from the cause.

This is [` + "`" + `fix-at-the-source` + "`" + `](../fix-at-the-source/SKILL.md) for the error channel — and the place the
[` + "`" + `absence` + "`" + `](../absence/SKILL.md) skill sends you when "missing" turns out to be a broken state.

### The one place you tolerate: a named outer boundary

Fail-hard does **not** mean every layer rethrows forever. It means failures travel *up* to **one explicit
boundary** that is allowed to absorb them — and even there, absorbing is **observable**, never silent. The
canonical shape: an untrusted-input decoder that catches per item, **logs**, and skips, then fails hard if
*nothing* survived.

Inside the system, invariants throw. At the *one* untrusted edge, you catch-log-skip. That's fail-hard
*and* resilient — not graceful-and-silent.`,
		Languages: []string{"php"},
		Related: []skill.Related{
			{Slug: "backend/fix-at-the-source", Reason: "surface the failure where it's born."},
			{Slug: "backend/absence", Reason: `absence routes "missing = broken state" here for the *how* of throwing; this skill routes "swallowed failure became an empty value" back there as the inverse smell.`},
		},
	}
}
