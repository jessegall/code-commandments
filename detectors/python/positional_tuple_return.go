package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// PositionalTupleReturnDetector finds a def returning a tuple of three or more values read from different places: a record known only by position.
type PositionalTupleReturnDetector struct{}

func init() {
	detectors.Register(catalog.Python, PositionalTupleReturnDetector{})
}

// Sin is the sin the detector finds.
func (PositionalTupleReturnDetector) Sin() sins.Sin {
	return pysins.PositionalTupleReturn{}
}

// Find is every place the sin is committed.
func (PositionalTupleReturnDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereKind("Tuple").
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.IsPositionalTuple)).
		Where(engine.As(py.Node.IsReturnedValue)).
		Reject(engine.As(py.Node.IsInSequenceFunction)).
		Reject(engine.As(program.IsInContractMethod)).
		Get()
}
