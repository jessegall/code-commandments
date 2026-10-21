package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ImpossibleGuardDetector finds a test for None on a value whose own annotation has already ruled it out.
type ImpossibleGuardDetector struct{}

func init() {
	detectors.Register(catalog.Python, ImpossibleGuardDetector{})
}

// Sin is the sin the detector finds.
func (ImpossibleGuardDetector) Sin() sins.Sin {
	return pysins.ImpossibleGuard{}
}

// Find is every guard a declared type has already ruled out.
func (ImpossibleGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereKind("Compare").
		Where(engine.As(program.GuardsWhatTheTypeRulesOut)).
		Get()
}
