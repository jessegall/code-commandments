package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ConstructorSideEffectDetector finds a class whose __init__ acts on a collaborator and throws the result away: building one changes something outside it.
type ConstructorSideEffectDetector struct{}

func init() {
	detectors.Register(catalog.Python, ConstructorSideEffectDetector{})
}

// Sin is the sin the detector finds.
func (ConstructorSideEffectDetector) Sin() sins.Sin {
	return pysins.ConstructorSideEffect{}
}

// Find is every place the sin is committed.
func (ConstructorSideEffectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereClass().
		Where(engine.As(py.Node.ConstructorHasSideEffect)).
		Get()
}
