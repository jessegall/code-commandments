package python

import (
	"path/filepath"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NamespaceCycleDetector finds the thinner direction of two packages importing each other.
type NamespaceCycleDetector struct{}

func init() {
	detectors.Register(catalog.Python, NamespaceCycleDetector{})
}

func (NamespaceCycleDetector) Sin() sins.Sin {
	return pysins.NamespaceCycle{}
}

func (NamespaceCycleDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).Program.PackageArrows().Independent(string(filepath.Separator)).ClosingAMutualPair()

}

func (NamespaceCycleDetector) CrossFile() {}
