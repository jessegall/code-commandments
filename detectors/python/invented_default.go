package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// InventedDefaultDetector finds a blank literal invented for a missing value: handed on as an argument, or returned when a lookup misses.
type InventedDefaultDetector struct{}

func init() {
	detectors.Register(catalog.Python, InventedDefaultDetector{})
}

func (InventedDefaultDetector) Sin() sins.Sin {
	return pysins.InventedDefault{}
}

func (InventedDefaultDetector) Find(codebase *engine.Codebase) []engine.Match {
	filled := py.In(codebase).
		WhereExpression().
		Where(engine.As(fallsBackToEmptyScalar)).
		Where(engine.As(py.Node.FillsArgument)).
		Reject(engine.As(py.Node.IsKeyedDefault)).
		Reject(engine.As(py.Node.FallsBackOnAGroupOfAMatch)).
		Get()
	returned := py.In(codebase).
		WhereStatement().
		Where(engine.As(returnsEmptyScalar)).
		Where(engine.As(isInInventingDef)).
		Get()

	return append(filled, returned...)
}

func returnsEmptyScalar(n py.Node) bool {
	return n.ReturnedValue().IsEmptyScalar()
}

func isInInventingDef(n py.Node) bool {
	return n.EnclosingFunction().IsInventingOnMiss()
}
