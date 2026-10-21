package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// HandedOutCollectionDetector finds a getter that returns the class's own mutable collection, untouched.
type HandedOutCollectionDetector struct{}

func init() {
	detectors.Register(catalog.Python, HandedOutCollectionDetector{})
}

// Sin is the sin the detector finds.
func (HandedOutCollectionDetector) Sin() sins.Sin {
	return pysins.HandedOutCollection{}
}

// Find is every getter handing out a collection of its class's own.
func (HandedOutCollectionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereFunction().
		Where(engine.As(py.Node.HandsOutItsOwnCollection)).
		Get()
}
