package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// RaiseWithoutCauseDetector finds a raise inside an except clause that drops the exception it caught.
type RaiseWithoutCauseDetector struct{}

func init() {
	detectors.Register(catalog.Python, RaiseWithoutCauseDetector{})
}

func (RaiseWithoutCauseDetector) Sin() sins.Sin {
	return pysins.RaiseWithoutCause{}
}

func (RaiseWithoutCauseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(py.Node.IsRaiseWithoutCause)).
		Get()
}
