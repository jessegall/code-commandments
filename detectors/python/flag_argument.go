package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// FlagArgumentDetector finds a def whose whole body is a two-way choice on one of its parameters: two defs sharing one name.
type FlagArgumentDetector struct{}

func init() {
	detectors.Register(catalog.Python, FlagArgumentDetector{})
}

func (FlagArgumentDetector) Sin() sins.Sin {
	return pysins.FlagArgument{}
}

func (FlagArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereFunction().
		Reject(engine.As(py.Node.IsConstructorDeclaration)).
		Where(engine.As(py.Node.SwitchesEntirelyOnAParameter)).
		Get()
}
