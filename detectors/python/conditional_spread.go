package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ConditionalSpreadDetector finds a spread of a conditional choosing between a collection and an empty one.
type ConditionalSpreadDetector struct{}

func init() {
	detectors.Register(catalog.Python, ConditionalSpreadDetector{})
}

func (ConditionalSpreadDetector) Sin() sins.Sin {
	return pysins.ConditionalSpread{}
}

func (ConditionalSpreadDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		Where(engine.As(py.Node.IsConditionalSpread)).
		Get()
}
