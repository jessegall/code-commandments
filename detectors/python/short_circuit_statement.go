package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ShortCircuitStatementDetector finds an `and` or an `or` standing as a statement for its side effect: an if written as an expression.
type ShortCircuitStatementDetector struct{}

func init() {
	detectors.Register(catalog.Python, ShortCircuitStatementDetector{})
}

// Sin is the sin the detector finds.
func (ShortCircuitStatementDetector) Sin() sins.Sin {
	return pysins.ShortCircuitStatement{}
}

// Find is every place the sin is committed.
func (ShortCircuitStatementDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereKind("BoolOp").
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.ResultIsDiscarded)).
		Get()
}
