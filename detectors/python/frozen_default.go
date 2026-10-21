package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// FrozenDefaultDetector finds a parameter defaulted to a call that answers differently every time it runs.
type FrozenDefaultDetector struct{}

func init() {
	detectors.Register(catalog.Python, FrozenDefaultDetector{})
}

// Sin is the sin the detector finds.
func (FrozenDefaultDetector) Sin() sins.Sin {
	return pysins.FrozenDefault{}
}

// Find is every parameter whose default is worked out once at definition.
func (FrozenDefaultDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereKind("arg").
		Where(engine.As(py.Node.IsFrozenDefault)).
		Get()
}
