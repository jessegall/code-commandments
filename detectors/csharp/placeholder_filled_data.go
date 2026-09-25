package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// PlaceholderFilledDataDetector finds a record of the codebase's own built with a blank string in a required `string` slot, outside its own Null Object and the tests.
type PlaceholderFilledDataDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, PlaceholderFilledDataDetector{})
}

// Sin is the sin the detector finds.
func (PlaceholderFilledDataDetector) Sin() sins.Sin {
	return cssins.PlaceholderFilledData{}
}

// Find is every place the sin is committed.
func (PlaceholderFilledDataDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		WhereKind("ObjectCreationExpression", "ImplicitObjectCreationExpression").
		Where(engine.As(program.FillsRecordWithBlank)).
		Reject(engine.As(cs.Node.IsNullObjectOfItsType)).
		Reject(engine.As(cs.Node.IsInTest)).
		Get()
}
