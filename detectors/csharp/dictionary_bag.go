package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// DictionaryBagDetector finds a record read by string keys written in the source, from a dictionary the code in hand owns or through a helper handed the key, outside where objects are built and the tests.
type DictionaryBagDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, DictionaryBagDetector{})
}

// Sin is the sin the detector finds.
func (DictionaryBagDetector) Sin() sins.Sin {
	return cssins.DictionaryBag{}
}

// Find is every place the sin is committed.
func (DictionaryBagDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program
	direct := cs.In(codebase).
		WhereExpression().
		Where(engine.As(cs.Node.IsStringKeyRead)).
		Where(engine.As(cs.Node.IsReadingOwnDictionary)).
		Reject(engine.As(cs.Node.IsAssignedTo)).
		Reject(engine.As(cs.Node.IsWithinOverride)).
		Reject(engine.As(cs.Node.IsWithinNamedConstructor)).
		Reject(engine.As(cs.Node.IsBuildingAnObject)).
		Reject(engine.As(cs.Node.IsInTest)).
		Get()
	throughHelper := cs.In(codebase).
		WhereCall().
		Where(engine.As(program.PassesLiteralKey)).
		Reject(engine.As(cs.Node.IsWithinNamedConstructor)).
		Reject(engine.As(cs.Node.IsBuildingAnObject)).
		Reject(engine.As(cs.Node.IsInTest)).
		Get()

	return append(direct, throughHelper...)
}
