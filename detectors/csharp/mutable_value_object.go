package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// MutableValueObjectDetector finds a record changed after construction: a `set` accessor left open, or a write to its state from a method, outside an override.
type MutableValueObjectDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, MutableValueObjectDetector{})
}

// Sin is the sin the detector finds.
func (MutableValueObjectDetector) Sin() sins.Sin {
	return cssins.MutableValueObject{}
}

// Find is every place the sin is committed.
func (MutableValueObjectDetector) Find(codebase *engine.Codebase) []engine.Match {
	setters := cs.In(codebase).
		WhereKind("SetAccessorDeclaration").
		Where(engine.As(cs.Node.IsRecordSetter)).
		Reject(engine.As(cs.Node.IsWithinOverride)).
		Get()
	writes := cs.In(codebase).
		WhereExpression().
		Where(engine.As(cs.Node.IsWrite)).
		Where(engine.As(cs.Node.IsWritingRecordState)).
		Reject(engine.As(cs.Node.IsWithinOverride)).
		Get()

	return append(setters, writes...)
}
