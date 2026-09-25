package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// MatchDefaultReturnsNullDetector finds a switch naming every member of an enum of the codebase's own that still answers nothing when none matches, a `false` in a try method aside.
type MatchDefaultReturnsNullDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, MatchDefaultReturnsNullDetector{})
}

// Sin is the sin the detector finds.
func (MatchDefaultReturnsNullDetector) Sin() sins.Sin {
	return cssins.MatchDefaultReturnsNull{}
}

// Find is every place the sin is committed.
func (MatchDefaultReturnsNullDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		WhereKind("SwitchExpression", "SwitchStatement").
		Where(engine.As(func(n cs.Node) bool { return n.FallbackValue().IsAbsenceValue() })).
		Where(engine.As(program.NamesEveryMember)).
		Reject(engine.As(func(n cs.Node) bool { return n.FallbackValue().Is("FalseLiteralExpression") && n.IsWithinTryMethod() })).
		Get()
}
