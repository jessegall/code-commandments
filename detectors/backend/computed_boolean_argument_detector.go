package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ComputedBooleanArgumentDetector finds a method deciding on bools that every caller computes from one and the same
// object: it should take the object and ask it itself.
type ComputedBooleanArgumentDetector struct{}

func init() { detectors.Register(catalog.Backend, ComputedBooleanArgumentDetector{}) }

// minCallers is how many callers must compute the bools before the method should take the object.
const minCallers = 2

// Sin is the sin the detector finds.
func (ComputedBooleanArgumentDetector) Sin() sins.Sin { return backendsins.ComputedBooleanArgument{} }

// WholeTree says the verdict reads every caller, in other files.
func (ComputedBooleanArgumentDetector) WholeTree() {}

// Find is every method branching on bool parameters alone whose two or more callers, one of them outside its class,
// all compute the arguments from one object of one class.
func (ComputedBooleanArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Where(engine.As(php.Node.DecidesOnBoolsAlone)).
		Where(func(n engine.Match) bool { return callersAllAskOneObject(codebase, n) }).
		Get()
}

func callersAllAskOneObject(codebase *engine.Codebase, declaration engine.Match) bool {
	class, name := php.EnclosingClassName(declaration), php.EnclosingFunctionName(declaration)
	if class == "" || name == "" {
		return false
	}
	callers := php.IndexOf(codebase).CallersOf(class, name)
	subjects := map[string]bool{}
	reachedFromOutside := false
	for _, call := range callers {
		subject := (php.Node{Match: call}).ArgumentSubjectType()
		if subject == "" {
			return false
		}
		reachedFromOutside = reachedFromOutside || php.EnclosingClassName(call) != class
		subjects[subject] = true
	}

	return reachedFromOutside && len(callers) >= minCallers && len(subjects) == 1
}
