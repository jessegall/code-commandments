package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// CancelledFallbackDetector finds a fallback to a blank literal compared to that very literal where it stands: `(name or "") == ""`.
type CancelledFallbackDetector struct{}

func init() {
	detectors.Register(catalog.Python, CancelledFallbackDetector{})
}

// Sin is the sin the detector finds.
func (CancelledFallbackDetector) Sin() sins.Sin {
	return pysins.CancelledFallback{}
}

// Find is every place the sin is committed.
func (CancelledFallbackDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereExpression().
		Where(engine.As(fallsBackToEmptyScalar)).
		Where(engine.As(py.Node.IsComparedToItsFallback)).
		Get()
}

// fallsBackToEmptyScalar says whether the expression falls back to an empty string, zero or False.
func fallsBackToEmptyScalar(n py.Node) bool {
	fallback, ok := n.Fallback()

	return ok && fallback.IsEmptyScalar()
}
