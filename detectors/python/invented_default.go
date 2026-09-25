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

// Sin is the sin the detector finds.
func (InventedDefaultDetector) Sin() sins.Sin {
	return pysins.InventedDefault{}
}

// Find is every place the sin is committed.
func (InventedDefaultDetector) Find(codebase *engine.Codebase) []engine.Match {
	filled := py.In(codebase).
		WhereExpression().
		Where(engine.As(fallsBackToEmptyScalar)).
		Where(engine.As(py.Node.FillsArgument)).
		Reject(engine.As(py.Node.IsKeyedDefault)).
		Get()
	returned := py.In(codebase).
		WhereStatement().
		Where(engine.As(returnsEmptyScalar)).
		Where(engine.As(isInInventingDef)).
		Get()

	return append(filled, returned...)
}

// returnsEmptyScalar says whether the statement returns an empty string, zero or False.
func returnsEmptyScalar(n py.Node) bool {
	return n.ReturnedValue().IsEmptyScalar()
}

// isInInventingDef says whether the statement sits in a def that answers a missed lookup with a blank literal.
func isInInventingDef(n py.Node) bool {
	return n.EnclosingFunction().IsInventingOnMiss()
}
