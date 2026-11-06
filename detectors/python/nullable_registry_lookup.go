package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NullableRegistryLookupDetector finds a method returning its own mapping's `get` for a key, None on a miss: a registry that should raise.
type NullableRegistryLookupDetector struct{}

func init() {
	detectors.Register(catalog.Python, NullableRegistryLookupDetector{})
}

func (NullableRegistryLookupDetector) Sin() sins.Sin {
	return pysins.NullableRegistryLookup{}
}

func (NullableRegistryLookupDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereCall().
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.IsReturnedValue)).
		Where(engine.As(py.Node.MissesToNone)).
		Where(engine.As(py.Node.LooksUpOwnDict)).
		Where(engine.As(func(n py.Node) bool { return program.NoCallerHandlesTheAbsence(n.EnclosingFunction()) })).
		Reject(engine.As(func(n py.Node) bool { return program.IsOverride(n.EnclosingFunction()) })).
		Get()
}
