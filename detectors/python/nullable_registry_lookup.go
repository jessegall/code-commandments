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
		// What the CALLERS do is the evidence the sin's own words claim: "so every caller decides what a miss
		// means", and the rule carves out the miss that is genuinely expected. So a store whose absence ONE
		// caller handles honestly is no sin: it says the miss may happen, and its callers do what the type asks.
		// Without this the rule fired on a cache of measurements a worker may not have yet (#815), where None
		// tells unavailable data from a measured zero and the reader is shown nothing rather than a nought.
		//
		// A store nothing calls yet is still the sin: its signature invites whatever calls it tomorrow to decide
		// the miss, which is what the named exception exists to settle once.
		Where(engine.As(func(n py.Node) bool { return program.NoCallerHandlesTheAbsence(n.EnclosingFunction()) })).
		Reject(engine.As(func(n py.Node) bool { return program.IsOverride(n.EnclosingFunction()) })).
		Get()
}
