package shared

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

const (
	duplicateWeight     = 12
	nearDuplicateWeight = 20
)

// DuplicateFunction finds functions that run the same body.
type DuplicateFunction struct{}

func (d DuplicateFunction) Find(codebase *engine.Codebase) []engine.Match {
	candidates := copyCandidates(codebase, duplicateWeight).Reject(engine.Match.ExcludesExactCopy).Get()

	return slices.Concat(engine.RecurringBuckets(candidates, d.GroupKey, 2)...)
}

func (DuplicateFunction) GroupKey(match engine.Match) (string, bool) {
	hash := match.BodyHash()

	return hash, hash != ""
}

func (DuplicateFunction) Sin() Text {
	return Text{
		Name:        "duplicate-function",
		Skill:       skills.Duplication{},
		Description: "Two functions repeat the same body",
		Rule:        "Write a shared function and call it from the places that need it.",
		Suggestion:  "Move the copied body to one owner and delegate to it from each caller.",
		Formerly: map[catalog.Engine]Former{
			catalog.TypeScript: {Sin: "duplicate-typescript-function"},
			catalog.Python:     {Sin: "duplicate-python-function"},
			catalog.CSharp:     {Sin: "duplicate-csharp-method", Detector: "DuplicateMethodDetector"},
		},
	}
}

func (DuplicateFunction) CrossFile() {}

// NearDuplicateFunction finds functions that differ only in names or literal values.
type NearDuplicateFunction struct{}

func (d NearDuplicateFunction) Find(codebase *engine.Codebase) []engine.Match {
	return d.FindRejecting(codebase, nil)
}

func (d NearDuplicateFunction) FindRejecting(codebase *engine.Codebase, reject engine.Check) []engine.Match {
	query := copyCandidates(codebase, nearDuplicateWeight).
		Reject(engine.Match.IsConstructor).
		Reject(engine.Match.IsSoleStatement).
		Reject(engine.Match.IsLiteralLookup).
		Reject(engine.Match.IsOverride).
		Reject(engine.Match.ExcludesNearCopy)
	if reject != nil {
		query = query.Reject(reject)
	}

	return engine.NearCopies(query.Get(), d.GroupKey, (DuplicateFunction{}).GroupKey)
}

func (NearDuplicateFunction) GroupKey(match engine.Match) (string, bool) {
	hash := match.BodyShape()

	return hash, hash != ""
}

func (NearDuplicateFunction) Sin() Text {
	return Text{
		Name:        "near-duplicate-function",
		Skill:       skills.Duplication{},
		Description: "Two functions run the same code with different names or data",
		Rule:        "Share the body and pass what differs as parameters.",
		Suggestion:  "Extract the common function and give its varying inputs names.",
		Formerly: map[catalog.Engine]Former{
			catalog.TypeScript: {Sin: "near-duplicate-typescript-function"},
			catalog.Python:     {Sin: "near-duplicate-python-function"},
			catalog.CSharp:     {Sin: "near-duplicate-csharp-method", Detector: "NearDuplicateMethodDetector"},
		},
	}
}

func (NearDuplicateFunction) CrossFile() {}

func copyCandidates(codebase *engine.Codebase, minimum int) *engine.Query {
	return codebase.Where(func(match engine.Match) bool { return match.BodyWeight() >= minimum }).
		Reject(engine.Match.IsStub).
		Reject(engine.Match.IsTest)
}
