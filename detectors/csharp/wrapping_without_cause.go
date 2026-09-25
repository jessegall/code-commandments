package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// WrappingWithoutCauseDetector finds a `throw new …` inside a `catch` that does not hand the caught exception on, losing the original stack trace.
type WrappingWithoutCauseDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, WrappingWithoutCauseDetector{})
}

// Sin is the sin the detector finds.
func (WrappingWithoutCauseDetector) Sin() sins.Sin {
	return cssins.WrappingWithoutCause{}
}

// Find is every place the sin is committed.
func (WrappingWithoutCauseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereKind("ThrowStatement", "ThrowExpression").
		Where(engine.As(cs.Node.IsWrappingWithoutCause)).
		Get()
}
