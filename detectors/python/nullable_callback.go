package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NullableCallbackDetector finds an optional callable parameter defaulted to None and asked whether it was given.
type NullableCallbackDetector struct{}

func init() {
	detectors.Register(catalog.Python, NullableCallbackDetector{})
}

// Sin is the sin the detector finds.
func (NullableCallbackDetector) Sin() sins.Sin {
	return pysins.NullableCallback{}
}

// Find is every place the sin is committed.
func (NullableCallbackDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereFunction().
		Where(engine.As(py.Node.HasNullNormalisedOptionalCallback)).
		Get()
}
