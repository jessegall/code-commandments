package csharp

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// RepeatedNamedCallDetector finds the same `with` copy of one type, changing the same slots to the same constants, written in two or more places outside the tests.
type RepeatedNamedCallDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, RepeatedNamedCallDetector{})
}

// Sin is the sin the detector finds.
func (RepeatedNamedCallDetector) Sin() sins.Sin {
	return cssins.RepeatedNamedCall{}
}

// Find is every place the sin is committed.
func (d RepeatedNamedCallDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

// Candidates is every call outside the tests that changes a constant, keyed by the change it makes.
func (d RepeatedNamedCallDetector) Candidates(codebase *engine.Codebase) []detectors.Candidate {
	copies := cs.In(codebase).
		WhereExpression().
		Where(engine.As(func(n cs.Node) bool { return len(n.ConstantChanges()) > 0 })).
		Reject(engine.As(cs.Node.IsInTest)).
		Get()

	return keyedBy(copies, d.GroupKey)
}

// Decide is every call another makes too.
func (RepeatedNamedCallDetector) Decide(candidates []detectors.Candidate) []int {
	return recurringAt(candidates, 2)
}

// GroupKey is the group a `with` copy recurs in: the type it copies and the constants it changes.
func (RepeatedNamedCallDetector) GroupKey(match engine.Match) (string, bool) {
	copied := cs.Node{Match: match}
	if len(copied.ConstantChanges()) == 0 {
		return "", false
	}

	return copied.Type().Name() + "#" + strings.Join(copied.ConstantChanges(), ","), true
}
