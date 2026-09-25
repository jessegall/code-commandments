package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// DeepNestingDetector finds a choice nested three choices deep in its function: the fourth level of indentation a guard would flatten.
type DeepNestingDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, DeepNestingDetector{})
}

// Sin is the sin the detector finds.
func (DeepNestingDetector) Sin() sins.Sin {
	return cssins.DeepNesting{}
}

// Find is every place the sin is committed.
func (DeepNestingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereStatement().
		Where(engine.As(cs.Node.IsBranchingConstruct)).
		Reject(engine.As(cs.Node.IsElseIf)).
		Where(engine.As(func(n cs.Node) bool { return n.BranchingDepth() == 3 })).
		Get()
}
