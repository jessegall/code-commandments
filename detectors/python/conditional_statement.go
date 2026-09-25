package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ConditionalStatementDetector finds a conditional expression standing as a statement, its value thrown away: an if written as an expression.
type ConditionalStatementDetector struct{}

func init() {
	detectors.Register(catalog.Python, ConditionalStatementDetector{})
}

// Sin is the sin the detector finds.
func (ConditionalStatementDetector) Sin() sins.Sin {
	return pysins.ConditionalStatement{}
}

// Find is every place the sin is committed.
func (ConditionalStatementDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereKind("IfExp").
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.ResultIsDiscarded)).
		Get()
}
