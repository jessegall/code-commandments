package shared

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// RepeatedGuard finds the same compound condition wherever it is written out.
type RepeatedGuard struct{}

func (r RepeatedGuard) Find(codebase *engine.Codebase) []engine.Match {
	return repeatedGuards(codebase, r.GroupKey)
}

func (RepeatedGuard) Occurrences(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereIs(engine.Conjunction).Get()
}

func (RepeatedGuard) GroupKey(match engine.Match) (string, bool) {
	guard := match.Guard()
	if !guard.Substantive() {
		return "", false
	}

	return guard.Key()
}

func (RepeatedGuard) Sin() Text {
	return Text{
		Name:        "repeated-guard",
		Skill:       skills.Duplication{},
		Description: "A compound condition about an object is written out at more than one site",
		Rule:        "Give a repeated condition one name and have the callers use it.",
		Suggestion:  "Put a named predicate on the object that owns most of the condition and use it at each site.",
		Formerly: map[catalog.Engine]Former{
			catalog.CSharp: {Sin: "csharp-repeated-guard"},
			catalog.Python: {Sin: "python-repeated-guard"},
		},
	}
}

func (RepeatedGuard) CrossFile() {}

// RepeatedTypeGuard finds the same combination of type checks in several conditions.
type RepeatedTypeGuard struct{}

func (r RepeatedTypeGuard) Find(codebase *engine.Codebase) []engine.Match {
	return repeatedGuards(codebase, r.GroupKey)
}

func (RepeatedTypeGuard) GroupKey(match engine.Match) (string, bool) {
	guard := match.Guard()
	if !guard.NarrowsTypes() {
		return "", false
	}

	return guard.Key()
}

func (RepeatedTypeGuard) Sin() Text {
	return Text{
		Name:        "repeated-type-guard",
		Skill:       skills.Duplication{},
		Description: "The same compound type check is written out at more than one site",
		Rule:        "Give a repeated type test one name and use it at each site.",
		Suggestion:  "Extract the combined test to a predicate that describes which values it accepts.",
		Formerly: map[catalog.Engine]Former{
			catalog.CSharp: {Sin: "csharp-repeated-type-guard"},
			catalog.Python: {Sin: "python-repeated-type-guard"},
		},
	}
}

func (RepeatedTypeGuard) CrossFile() {}

func repeatedGuards(codebase *engine.Codebase, key func(engine.Match) (string, bool)) []engine.Match {
	guards := codebase.WhereIs(engine.Conjunction).Get()

	return slices.Concat(engine.RecurringBuckets(guards, key, 2)...)
}
