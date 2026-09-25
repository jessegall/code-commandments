package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// SwallowedExceptionDetector finds an except clause catching everything and doing nothing with it.
type SwallowedExceptionDetector struct{}

func init() {
	detectors.Register(catalog.Python, SwallowedExceptionDetector{})
}

// Sin is the sin the detector finds.
func (SwallowedExceptionDetector) Sin() sins.Sin {
	return pysins.SwallowedException{}
}

// Find is every place the sin is committed.
func (SwallowedExceptionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereKind("ExceptHandler").
		Where(engine.As(py.Node.IsBroad)).
		Where(engine.As(py.Node.Swallows)).
		Get()
}
