package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// InventedDefaultDetector finds an empty value invented for a missing one: a fallback of `""`, `0` or `false` handed straight to a call, or a member answering a lookup miss with one.
type InventedDefaultDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, InventedDefaultDetector{})
}

// Sin is the sin the detector finds.
func (InventedDefaultDetector) Sin() sins.Sin {
	return cssins.InventedDefault{}
}

// Find is every place the sin is committed.
func (InventedDefaultDetector) Find(codebase *engine.Codebase) []engine.Match {
	filled := cs.In(codebase).
		WhereExpression().
		Where(engine.As(func(n cs.Node) bool { return n.Fallback().IsEmptyScalar() })).
		Where(engine.As(cs.Node.FillsArgument)).
		Get()
	answered := cs.In(codebase).
		WhereFunction().
		Where(engine.As(cs.Node.IsInventingOnMiss)).
		Get()

	return append(filled, answered...)
}
