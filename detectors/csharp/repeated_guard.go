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
func (RepeatedGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	guards := cs.In(codebase).
		WhereExpression().
		Where(engine.As(cs.Node.IsSubstantiveGuard)).
		Where(engine.As(cs.Node.IsOutermostAnd)).
		Reject(engine.As(cs.Node.IsStoredValue)).
		Get()

	return recurring(guards, guardFingerprint)
}

// guardFingerprint is what the guard asks, whatever order its conditions are written in.
func guardFingerprint(match engine.Match) (string, bool) {
	return cs.Node{Match: match}.GuardFingerprint(), true
}

// recurring is every candidate whose key two or more of them read as.
func recurring(candidates []engine.Match, key func(engine.Match) (string, bool)) []engine.Match {
	var found []engine.Match
	for _, bucket := range engine.RecurringBuckets(candidates, key, 2) {
		found = append(found, bucket...)
	}

	return found
}

// GroupKey is the group a guard recurs in: what it asks, whatever order its conditions are in.
func (RepeatedGuardDetector) GroupKey(match engine.Match) (string, bool) {
	if !(cs.Node{Match: match}).IsSubstantiveGuard() {
		return "", false
	}

	return guardFingerprint(match)
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (RepeatedGuardDetector) WholeTree() {}
