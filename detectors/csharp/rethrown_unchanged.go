package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// RethrownUnchangedDetector finds a catch clause whose whole body throws the failure it caught, unchanged.
type RethrownUnchangedDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, RethrownUnchangedDetector{})
}

// Sin is the sin the detector finds.
func (RethrownUnchangedDetector) Sin() sins.Sin {
	return cssins.RethrownUnchanged{}
}

// Find is every catch clause that only throws what it caught.
func (RethrownUnchangedDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereKind("CatchClause").
		Where(engine.As(cs.Node.RethrowsUnchanged)).
		Get()
}
