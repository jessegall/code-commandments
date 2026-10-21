package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// RethrownUnchangedDetector finds an except clause whose whole body raises the failure it caught, unchanged.
type RethrownUnchangedDetector struct{}

func init() {
	detectors.Register(catalog.Python, RethrownUnchangedDetector{})
}

// Sin is the sin the detector finds.
func (RethrownUnchangedDetector) Sin() sins.Sin {
	return pysins.RethrownUnchanged{}
}

// Find is every except clause that only raises what it caught.
func (RethrownUnchangedDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereKind("ExceptHandler").
		Where(engine.As(py.Node.RethrowsUnchanged)).
		Get()
}
