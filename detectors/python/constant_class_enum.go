package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ConstantClassEnumDetector finds a plain class of scalar constants: a closed set of values written without an enum.
type ConstantClassEnumDetector struct{}

func init() {
	detectors.Register(catalog.Python, ConstantClassEnumDetector{})
}

// Sin is the sin the detector finds.
func (ConstantClassEnumDetector) Sin() sins.Sin {
	return pysins.ConstantClassEnum{}
}

// Find is every place the sin is committed.
func (ConstantClassEnumDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereClass().
		Where(engine.As(py.Node.IsScalarConstantClass)).
		Get()
}
