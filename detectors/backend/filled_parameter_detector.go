package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// FilledParameterDetector finds a void method that writes a property of an object it was handed, whose caller
// then reads what it handed over: the value travels back through the argument, and the signature says nothing.
type FilledParameterDetector struct{}

func init() { detectors.Register(catalog.PHP, FilledParameterDetector{}) }

// Sin is the sin the detector finds.
func (FilledParameterDetector) Sin() sins.Sin { return backendsins.FilledParameter{} }

// WholeTree says the verdict needs the callers, and callers live in other files.
func (FilledParameterDetector) WholeTree() {}

// Exemptions excuses a method whose signature the framework dictates.
func (FilledParameterDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.ContractMethod, By: []packages.By{packages.EnclosingMethod}}}
}

// Find is every void method that fills a parameter a caller reads after the call.
func (d FilledParameterDetector) Find(codebase *engine.Codebase) []engine.Match {
	return packages.Exempt(codebase, d, codebase.
		WhereKind("Stmt_ClassMethod").
		Where(func(n engine.Match) bool { return fillsWhatACallerThenReads(codebase, n) }).
		Get())
}

// fillsWhatACallerThenReads says whether the method fills one of its parameters and a caller reads the very
// variable it handed over, after handing it over. Without that read the method is a command and no business of
// this rule: someone has to be waiting for the change for the signature's silence to cost anything.
func fillsWhatACallerThenReads(codebase *engine.Codebase, method engine.Match) bool {
	held := php.Node{Match: method}
	filled, fills := held.FilledParameter()
	class, name := php.EnclosingClassName(method), php.EnclosingFunctionName(method)
	if !fills || class == "" || name == "" {
		return false
	}
	// A method answering a signature it did not write owns none of it: one an ancestor declares cannot start
	// returning a value, so the silence is not its to break.
	if held.NameIsInherited() {
		return false
	}
	at := php.ParameterPosition(method, filled)
	for _, caller := range php.IndexOf(codebase).CallersOf(class, name) {
		if php.ArgumentIsReadAfterTheCall(caller, at) {
			return true
		}
	}

	return false
}
