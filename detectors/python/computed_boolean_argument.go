package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
	"slices"
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
		Where(engine.As(func(n py.Node) bool { return callersAllAskOneObject(program, n) })).
		Get()
}

// callersAllAskOneObject says whether two or more calls reach the method, one of them from outside its class, and
// every one computes every argument from one and the same class of object.
func callersAllAskOneObject(program *py.Program, method py.Node) bool {
	class := method.Parent()
	callers := program.CallersOf(method)
	var subjects []string
	fromOutside := false
	for _, call := range callers {
		subject, ok := call.ArgumentSubjectType()
		if !ok {
			return false
		}
		if !slices.Contains(subjects, subject) {
			subjects = append(subjects, subject)
		}
		fromOutside = fromOutside || call.EnclosingFunction().Parent() != class
	}

	return fromOutside && len(callers) >= computedCallers && len(subjects) == 1
}
