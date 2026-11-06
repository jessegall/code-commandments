package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// FilledParameterDetector finds a method returning nothing that writes a property of an object it was handed, whose caller then reads what it handed over: the value travels back through the argument, and the signature is silent about it.
type FilledParameterDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, FilledParameterDetector{})
}

func (FilledParameterDetector) Sin() sins.Sin {
	return cssins.FilledParameter{}
}

func (FilledParameterDetector) WholeTree() {}

func (FilledParameterDetector) Find(codebase *engine.Codebase) []engine.Match {
	calls := cs.CallsBySymbol(codebase)

	return cs.In(codebase).
		WhereKind("MethodDeclaration").
		Where(engine.As(func(n cs.Node) bool { return fillsWhatACallerThenReads(calls, n) })).
		Get()
}

func fillsWhatACallerThenReads(calls map[string][]cs.Node, method cs.Node) bool {
	filled, fills := method.FilledParameter()
	if !fills || method.FillsOutsideTheSolution() {
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
