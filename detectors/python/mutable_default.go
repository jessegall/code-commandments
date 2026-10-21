package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// MutableDefaultDetector finds a parameter defaulted to a container: one container, made once when the def is
// defined, shared by every call that leaves the parameter out.
type MutableDefaultDetector struct{}

func init() {
	detectors.Register(catalog.Python, MutableDefaultDetector{})
}

// Sin is the sin the detector finds.
func (MutableDefaultDetector) Sin() sins.Sin {
	return pysins.MutableDefault{}
}

// Find is every parameter whose default builds a container of its own.
func (MutableDefaultDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereKind("arg").
		Where(engine.As(py.Node.IsMutableDefault)).
		Reject(engine.As(py.Node.DeclaresNoBody)).
		Get()
}
