package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// NestedTernaryDetector finds a conditional expression with another as one of its branches, reported once, at the outermost.
type NestedTernaryDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, NestedTernaryDetector{})
}

func (NestedTernaryDetector) Sin() sins.Sin {
	return cssins.NestedTernary{}
}

func (NestedTernaryDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereExpression().
		Where(engine.As(cs.Node.IsNestedConditional)).
		Reject(engine.As(cs.Node.IsConditionalBranch)).
		Get()
}
