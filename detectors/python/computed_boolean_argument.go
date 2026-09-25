package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// computedCallers is how many callers must compute the flags from one object.
const computedCallers = 2

// ComputedBooleanArgumentDetector finds a method deciding on bools alone that every caller computes from one object it hands none of: take the object.
type ComputedBooleanArgumentDetector struct{}

func init() {
	detectors.Register(catalog.Python, ComputedBooleanArgumentDetector{})
}

// Sin is the sin the detector finds.
func (ComputedBooleanArgumentDetector) Sin() sins.Sin {
	return pysins.ComputedBooleanArgument{}
}

// Find is every place the sin is committed.
func (ComputedBooleanArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(py.Node.DecidesOnBoolsAlone)).
		Where(engine.As(func(n py.Node) bool { return program.CallersAllAskOneObject(n, computedCallers) })).
		Get()
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (ComputedBooleanArgumentDetector) CrossFile() {}
