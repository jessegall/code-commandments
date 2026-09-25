package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// DerivedArgumentDetector finds a parameter every call fills with a piece of an object it also hands over, or with
// one of three or more pieces of one object: the def should take the object and read the piece itself.
type DerivedArgumentDetector struct{}

func init() {
	detectors.Register(catalog.Python, DerivedArgumentDetector{})
}

// Sin is the sin the detector finds.
func (DerivedArgumentDetector) Sin() sins.Sin {
	return pysins.DerivedArgument{}
}

// Find is every place the sin is committed.
func (DerivedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return matches(py.In(codebase).Program.DerivedArgumentCalls())
}
