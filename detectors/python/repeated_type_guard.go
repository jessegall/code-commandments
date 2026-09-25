package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// RepeatedTypeGuardDetector finds the same run of isinstance checks written at two or more sites.
type RepeatedTypeGuardDetector struct{}

func init() {
	detectors.Register(catalog.Python, RepeatedTypeGuardDetector{})
}

// Sin is the sin the detector finds.
func (RepeatedTypeGuardDetector) Sin() sins.Sin {
	return pysins.RepeatedTypeGuard{}
}

// Find is every place the sin is committed.
func (RepeatedTypeGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	guards := py.In(codebase).
		WhereKind("BoolOp").
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.IsTypeNarrowingGuard)).
		Get()

	return flatten(engine.RecurringBuckets(guards, guardFingerprint, 2))
}

// GroupKey groups a finding with the type checks it repeats.
func (RepeatedTypeGuardDetector) GroupKey(match engine.Match) (string, bool) {
	return guardFingerprint(match)
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (RepeatedTypeGuardDetector) WholeTree() {}
