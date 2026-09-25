package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// MutableStaticStateDetector finds a def writing state that outlives every call: a global, or its class's attribute.
type MutableStaticStateDetector struct{}

func init() {
	detectors.Register(catalog.Python, MutableStaticStateDetector{})
}

// Sin is the sin the detector finds.
func (MutableStaticStateDetector) Sin() sins.Sin {
	return pysins.MutableStaticState{}
}

// Find is every place the sin is committed.
func (MutableStaticStateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(py.Node.IsStaticStateWrite)).
		Get()
}
