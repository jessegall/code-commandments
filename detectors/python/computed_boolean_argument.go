package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

const computedCallers = 2

// ComputedBooleanArgumentDetector finds a method deciding on bools alone that every caller computes from one object it hands none of: take the object.
type ComputedBooleanArgumentDetector struct{}

func init() {
	detectors.Register(catalog.Python, ComputedBooleanArgumentDetector{})
}

func (ComputedBooleanArgumentDetector) Sin() sins.Sin {
	return pysins.ComputedBooleanArgument{}
}

func (ComputedBooleanArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(py.Node.DecidesOnBoolsAlone)).
		Where(engine.As(func(n py.Node) bool { return program.CallersAllAskOneObject(n, computedCallers) })).
		Get()
}

func (ComputedBooleanArgumentDetector) CrossFile() {}
