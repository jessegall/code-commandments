package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// MutableStaticStateDetector finds a write to a static field of the type's own that anything may overwrite, from a method or accessor.
type MutableStaticStateDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, MutableStaticStateDetector{})
}

// Sin is the sin the detector finds.
func (MutableStaticStateDetector) Sin() sins.Sin {
	return cssins.MutableStaticState{}
}

// Find is every place the sin is committed.
func (MutableStaticStateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereExpression().
		Where(engine.As(func(n cs.Node) bool { return len(n.All()) > 0 })).
		Where(engine.As(cs.Node.IsWritingStaticState)).
		Get()
}
