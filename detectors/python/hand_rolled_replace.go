package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// HandRolledReplaceDetector finds a method rebuilding its own dataclass, carrying most fields across by hand.
type HandRolledReplaceDetector struct{}

func init() {
	detectors.Register(catalog.Python, HandRolledReplaceDetector{})
}

// Sin is the sin the detector finds.
func (HandRolledReplaceDetector) Sin() sins.Sin {
	return pysins.HandRolledReplace{}
}

// Find is every place the sin is committed.
func (HandRolledReplaceDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereCall().
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.IsHandRolledReplace)).
		Get()
}
