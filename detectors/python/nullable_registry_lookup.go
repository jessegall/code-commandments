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
		// What the CALLERS do is the evidence, and the same measure the PHP rule uses: a store hands out
		// `X | None` and every caller outside the tests, two or more, turns that None into a failure, so the
		// absence is impossible and the store's own type says otherwise. Without it the rule fired on a store
		// whose absence is honest and whose callers say so — a cache of measurements a worker may not have yet
		// (#815), where None tells unavailable data apart from a measured zero and the viewer hides the figure.
		Where(engine.As(func(n py.Node) bool { return program.IsDeNulledFinder(n.EnclosingFunction()) })).
		Reject(engine.As(func(n py.Node) bool { return program.IsOverride(n.EnclosingFunction()) })).
		Get()
}
