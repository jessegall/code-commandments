package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// ConstructorSideEffectDetector finds a constructor telling a collaborator it was handed to act, the answer thrown away.
type ConstructorSideEffectDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, ConstructorSideEffectDetector{})
}

// Sin is the sin the detector finds.
func (ConstructorSideEffectDetector) Sin() sins.Sin {
	return cssins.ConstructorSideEffect{}
}

// Find is every place the sin is committed.
func (ConstructorSideEffectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereCall().
		Where(engine.As(cs.Node.IsConstructorSideEffect)).
		Get()
}
