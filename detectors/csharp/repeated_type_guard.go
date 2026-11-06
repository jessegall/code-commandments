package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// RepeatedTypeGuardDetector finds the same chain of two or more type checks narrowing a value, written in two or more places.
type RepeatedTypeGuardDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, RepeatedTypeGuardDetector{})
}

func (RepeatedTypeGuardDetector) Sin() sins.Sin {
	return cssins.RepeatedTypeGuard{}
}

func (d RepeatedTypeGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

func (RepeatedTypeGuardDetector) Candidates(codebase *engine.Codebase) []detectors.Candidate {
	guards := cs.In(codebase).
		WhereExpression().
		Where(engine.As(cs.Node.IsTypeNarrowingGuard)).
		Where(engine.As(cs.Node.IsOutermostAnd)).
		Get()

	return keyedBy(guards, guardFingerprint)
}

func (RepeatedTypeGuardDetector) Decide(candidates []detectors.Candidate) []int {
	return recurringAt(candidates, 2)
}

func (RepeatedTypeGuardDetector) GroupKey(match engine.Match) (string, bool) {
	if !(cs.Node{Match: match}).IsTypeNarrowingGuard() {
		return "", false
	}

	return guardFingerprint(match)
}

func (RepeatedTypeGuardDetector) CrossFile() {}
