package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// CoalescedLoopSubjectDetector finds a `foreach` over `x ?? []`: a collection defended against a null it should never be.
type CoalescedLoopSubjectDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, CoalescedLoopSubjectDetector{})
}

// Sin is the sin the detector finds.
func (CoalescedLoopSubjectDetector) Sin() sins.Sin {
	return cssins.CoalescedLoopSubject{}
}

// Find is every place the sin is committed.
func (CoalescedLoopSubjectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereKind("ForEachStatement").
		Where(engine.As(func(loop cs.Node) bool { return loop.Expressions()[0].Is("CoalesceExpression") })).
		Where(engine.As(func(loop cs.Node) bool { return loop.Expressions()[0].Fallback().IsEmptyCollection() })).
		Get()
}
