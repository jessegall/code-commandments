package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// DeNulledFinderDetector finds a def answering `X | None` that every caller outside the tests, two or more, turns into a failure.
type DeNulledFinderDetector struct{}

func init() {
	detectors.Register(catalog.Python, DeNulledFinderDetector{})
}

// Sin is the sin the detector finds.
func (DeNulledFinderDetector) Sin() sins.Sin {
	return pysins.DeNulledFinder{}
}

// Find is every finder whose callers all decide the absence for it.
func (DeNulledFinderDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereFunction().
		Where(engine.As(program.IsDeNulledFinder)).
		Get()
}

// CrossFile says the rule reads beyond the file it judges, so a per-file check must not ask it.
func (DeNulledFinderDetector) CrossFile() {}
