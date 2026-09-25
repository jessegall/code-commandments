package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// MaskedInvariantDetector finds a literal answering an absent scratch field of the object's own.
type MaskedInvariantDetector struct{}

func init() {
	detectors.Register(catalog.Python, MaskedInvariantDetector{})
}

// Sin is the sin the detector finds.
func (MaskedInvariantDetector) Sin() sins.Sin {
	return pysins.MaskedInvariant{}
}

// Find is every place the sin is committed.
func (MaskedInvariantDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereExpression().
		Where(engine.As(py.Node.MasksOwnState)).
		Get()
}
