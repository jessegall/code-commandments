package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// FilledParameterDetector finds a method returning nothing that writes a property of an object it was handed,
// whose caller then reads what it handed over: the value travels back through the argument, and the signature
// is silent about it.
type FilledParameterDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, FilledParameterDetector{})
}

// Sin is the sin the detector finds.
func (FilledParameterDetector) Sin() sins.Sin {
	return cssins.FilledParameter{}
}

// WholeTree says the verdict needs the callers, and callers live in other files.
func (FilledParameterDetector) WholeTree() {}

// Find is every method that fills a parameter a caller reads after the call.
func (FilledParameterDetector) Find(codebase *engine.Codebase) []engine.Match {
	calls := cs.CallsBySymbol(codebase)

	return cs.In(codebase).
		WhereKind("MethodDeclaration").
		Where(engine.As(func(n cs.Node) bool { return fillsWhatACallerThenReads(calls, n) })).
		Get()
}

// fillsWhatACallerThenReads says whether the method fills one of its parameters and a caller reads the very
// name it handed over, after handing it over. Without that read the method is a command and no business of
// this rule: someone has to be waiting for the change for the signature's silence to cost anything. A method
// answering a signature it did not write owns none of it, so it cannot start returning a value either.
func fillsWhatACallerThenReads(calls map[string][]cs.Node, method cs.Node) bool {
	filled, fills := method.FilledParameter()
	if !fills || method.IsInherited() {
		return false
	}
	at := method.ParameterPosition(filled)
	for _, caller := range calls[method.Symbol()] {
		if caller.ArgumentIsReadAfterTheCall(at) {
			return true
		}
	}

	return false
}
