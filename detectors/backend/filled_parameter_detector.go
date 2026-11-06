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

// FilledParameterDetector finds a void method that writes a property of an object it was handed, whose caller then reads what it handed over: the value travels back through the argument, and the signature says nothing.
type FilledParameterDetector struct{}

func init() { detectors.Register(catalog.PHP, FilledParameterDetector{}) }

func (FilledParameterDetector) Sin() sins.Sin { return backendsins.FilledParameter{} }

func (FilledParameterDetector) WholeTree() {}

func (FilledParameterDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.ContractMethod, By: []packages.By{packages.EnclosingMethod}}}
}

func (d FilledParameterDetector) Find(codebase *engine.Codebase) []engine.Match {
	return packages.Exempt(codebase, d, codebase.
		WhereKind("Stmt_ClassMethod").
		Where(func(n engine.Match) bool { return fillsWhatACallerThenReads(codebase, n) }).
		Get())
}

func fillsWhatACallerThenReads(codebase *engine.Codebase, method engine.Match) bool {
	held := php.Node{Match: method}
	filled, fills := held.FilledParameter()
	class, name := php.EnclosingClassName(method), php.EnclosingFunctionName(method)
	if !fills || class == "" || name == "" {
		return false
	}
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
