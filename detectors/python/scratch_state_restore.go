package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ScratchStateRestoreDetector finds a def that saves one of its own attributes into a local and writes it back later.
type ScratchStateRestoreDetector struct{}

func init() {
	detectors.Register(catalog.Python, ScratchStateRestoreDetector{})
}

// Sin is the sin the detector finds.
func (ScratchStateRestoreDetector) Sin() sins.Sin {
	return pysins.ScratchStateRestore{}
}

// Find is every place the sin is committed.
func (ScratchStateRestoreDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereFunction().
		Where(engine.As(py.Node.HasOwnStateSaveAndRestore)).
		Get()
}
