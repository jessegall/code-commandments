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

// Sin is the sin the detector finds.
func (RepeatedTypeGuardDetector) Sin() sins.Sin {
	return cssins.RepeatedTypeGuard{}
}

// Find is every place the sin is committed.
func (RepeatedTypeGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	guards := cs.In(codebase).
		WhereExpression().
		Where(engine.As(cs.Node.IsTypeNarrowingGuard)).
		Where(engine.As(cs.Node.IsOutermostAnd)).
		Get()

	return recurring(guards, guardFingerprint)
}

// GroupKey is the group a type-narrowing guard recurs in: what it asks, whatever order its checks are in.
func (RepeatedTypeGuardDetector) GroupKey(match engine.Match) (string, bool) {
	if !(cs.Node{Match: match}).IsTypeNarrowingGuard() {
		return "", false
	}

	return guardFingerprint(match)
}
