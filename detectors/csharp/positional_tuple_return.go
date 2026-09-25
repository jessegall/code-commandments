package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// PositionalTupleReturnDetector finds a member returning a tuple whose unnamed slots share a type, so two can be swapped and nothing notices.
type PositionalTupleReturnDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, PositionalTupleReturnDetector{})
}

// Sin is the sin the detector finds.
func (PositionalTupleReturnDetector) Sin() sins.Sin {
	return cssins.PositionalTupleReturn{}
}

// Find is every place the sin is committed.
func (PositionalTupleReturnDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		Where(engine.As(cs.Node.ReturnsPositionalTuple)).
		Reject(engine.As(cs.Node.IsInherited)).
		Get()
}
