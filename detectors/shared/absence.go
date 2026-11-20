package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// CancelledCoalesce is a fallback compared against the very value it falls back to.
type CancelledCoalesce struct{}

func (CancelledCoalesce) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.IsComparedToItsFallback).
		Get()
}

func (CancelledCoalesce) Sin() Text {
	return Text{
		Name:        "cancelled-coalesce",
		Skill:       skills.Absence{},
		Description: "A fallback compared against the same value it falls back to (`(name ?? '') !== ''`, `(name or \"\") != \"\"`): missing and empty end up in one branch without saying so",
		Rule:        "Ask about absence directly; never fall back to a value only to compare against that same value.",
		Suggestion:  "Say both halves out loud (`name !== null && name !== ''`), or make the value always present where it is made so only one question is left.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-cancelled-fallback", Detector: "CancelledFallbackDetector"},
			catalog.CSharp: {Sin: "csharp-cancelled-coalesce"},
		},
	}
}
