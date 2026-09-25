package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// CancelledCoalesceDetector finds a fallback compared against the very value it falls back to, so it only ever cancels itself.
type CancelledCoalesceDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, CancelledCoalesceDetector{})
}

// Sin is the sin the detector finds.
func (CancelledCoalesceDetector) Sin() sins.Sin {
	return cssins.CancelledCoalesce{}
}

// Find is every place the sin is committed.
func (CancelledCoalesceDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereExpression().
		Where(engine.As(func(n cs.Node) bool { return n.Fallback().Exists() })).
		Where(engine.As(cs.Node.IsComparedToItsFallback)).
		Get()
}
