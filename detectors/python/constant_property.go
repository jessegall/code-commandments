package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ConstantPropertyDetector finds a property that returns one literal answer every time and keeps no contract.
type ConstantPropertyDetector struct{}

func init() {
	detectors.Register(catalog.Python, ConstantPropertyDetector{})
}

// Sin is the sin the detector finds.
func (ConstantPropertyDetector) Sin() sins.Sin {
	return pysins.ConstantProperty{}
}

// Find is every place the sin is committed.
func (ConstantPropertyDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(program.IsConstantProperty)).
		Get()
}
