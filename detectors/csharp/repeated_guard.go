package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// RepeatedGuardDetector finds the same compound condition about data asked in two or more places, whatever order its conditions are in.
type RepeatedGuardDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, RepeatedGuardDetector{})
}

// Sin is the sin the detector finds.
func (RepeatedGuardDetector) Sin() sins.Sin {
	return cssins.RepeatedGuard{}
}

// Find is every place the sin is committed.
func (d RepeatedGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

// Candidates is every substantive guard, keyed by what it asks.
func (RepeatedGuardDetector) Candidates(codebase *engine.Codebase) []detectors.Candidate {
	guards := cs.In(codebase).
		WhereExpression().
		Where(engine.As(cs.Node.IsSubstantiveGuard)).
		Where(engine.As(cs.Node.IsOutermostAnd)).
		Reject(engine.As(cs.Node.IsStoredValue)).
		Get()

	return keyedBy(guards, guardFingerprint)
}

// Decide is every guard another asks too.
func (RepeatedGuardDetector) Decide(candidates []detectors.Candidate) []int {
	return recurringAt(candidates, 2)
}

// guardFingerprint is what the guard asks, whatever order its conditions are written in.
func guardFingerprint(match engine.Match) (string, bool) {
	return cs.Node{Match: match}.GuardFingerprint(), true
}

// GroupKey is the group a guard recurs in: what it asks, whatever order its conditions are in.
func (RepeatedGuardDetector) GroupKey(match engine.Match) (string, bool) {
	if !(cs.Node{Match: match}).IsSubstantiveGuard() {
		return "", false
	}

	return guardFingerprint(match)
}
