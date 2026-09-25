package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// RawDecodedReturnDetector finds a def returning what JSON decoding gave it, untyped.
type RawDecodedReturnDetector struct{}

func init() {
	detectors.Register(catalog.Python, RawDecodedReturnDetector{})
}

// Sin is the sin the detector finds.
func (RawDecodedReturnDetector) Sin() sins.Sin {
	return pysins.RawDecodedReturn{}
}

// Find is every place the sin is committed.
func (RawDecodedReturnDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereCall().
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.IsJSONDecode)).
		Where(engine.As(py.Node.IsReturnedValue)).
		Reject(engine.As(py.Node.DecodesItsOwnEncoding)).
		Reject(engine.As(program.IsInTypedDictFunction)).
		Reject(engine.As(program.IsInContractMethod)).
		Get()
}
