package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// LoopWrappedInIfDetector finds a loop whose whole body is an if guarding its work: a guard to invert into a `continue`.
type LoopWrappedInIfDetector struct{}

func init() {
	detectors.Register(catalog.Python, LoopWrappedInIfDetector{})
}

// Sin is the sin the detector finds.
func (LoopWrappedInIfDetector) Sin() sins.Sin {
	return pysins.LoopWrappedInIf{}
}

// Find is every place the sin is committed.
func (LoopWrappedInIfDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(py.Node.IsSoleLoopBodyGuard)).
		Get()
}
