package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NamespaceCycleDetector finds the thinner direction of two packages importing each other.
type NamespaceCycleDetector struct{}

func init() {
	detectors.Register(catalog.Python, NamespaceCycleDetector{})
}

// Sin is the sin the detector finds.
func (NamespaceCycleDetector) Sin() sins.Sin {
	return pysins.NamespaceCycle{}
}

// Find is every place the sin is committed.
func (NamespaceCycleDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).Program.PackageArrows().ClosingAMutualPair()

}
