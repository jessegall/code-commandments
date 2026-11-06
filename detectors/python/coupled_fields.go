package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// CoupledFieldsDetector finds a class whose own value fields are really one object.
type CoupledFieldsDetector struct{}

func init() {
	detectors.Register(catalog.Python, CoupledFieldsDetector{})
}

func (CoupledFieldsDetector) Sin() sins.Sin {
	return pysins.CoupledFields{}
}

func (CoupledFieldsDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereClass().
		Where(engine.As(program.IsCoupled)).
		Get()
}

func (CoupledFieldsDetector) CrossFile() {}
