package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NullableRegistryLookupDetector finds a method returning its own mapping's `get` for a key, None on a miss: a registry that should raise.
type NullableRegistryLookupDetector struct{}

func init() {
	detectors.Register(catalog.Python, NullableRegistryLookupDetector{})
}

// Sin is the sin the detector finds.
func (NullableRegistryLookupDetector) Sin() sins.Sin {
	return pysins.NullableRegistryLookup{}
}

// Find is every place the sin is committed.
func (NullableRegistryLookupDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereCall().
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.IsReturnedValue)).
		Where(engine.As(py.Node.MissesToNone)).
		Where(engine.As(py.Node.LooksUpOwnDict)).
		Reject(engine.As(func(n py.Node) bool { return program.IsOverride(n.EnclosingFunction()) })).
		Get()
}
