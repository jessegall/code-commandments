package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// BlankStringDefaultDetector finds a `str` parameter or field defaulted to "" that the code then asks is blank: a missing value spelled as an empty one.
type BlankStringDefaultDetector struct{}

func init() {
	detectors.Register(catalog.Python, BlankStringDefaultDetector{})
}

// Sin is the sin the detector finds.
func (BlankStringDefaultDetector) Sin() sins.Sin {
	return pysins.BlankStringDefault{}
}

// Find is every place the sin is committed.
func (BlankStringDefaultDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereKind("arg", "AnnAssign").
		Where(engine.As(py.Node.IsBlankStringDefault)).
		Where(engine.As(py.Node.DefaultedNameTestedForBlankness)).
		Reject(engine.As(program.IsFieldOfDataBuiltClass)).
		Reject(engine.As(program.IsParameterOfADispatchedMethod)).
		Reject(engine.As(program.IsParameterEveryCallFills)).
		Get()
}
