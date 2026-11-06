package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// FilledParameterDetector finds a def returning None that writes an attribute of an object it was handed, whose caller then reads what it handed over: the value travels back through the argument, and the signature is silent.
type FilledParameterDetector struct{}

func init() {
	detectors.Register(catalog.Python, FilledParameterDetector{})
}

func (FilledParameterDetector) Sin() sins.Sin {
	return pysins.FilledParameter{}
}

func (FilledParameterDetector) WholeTree() {}

func (FilledParameterDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereFunction().
		Where(engine.As(func(n py.Node) bool { return fillsWhatACallerThenReads(codebase, n) })).
		Get()
}

func fillsWhatACallerThenReads(codebase *engine.Codebase, def py.Node) bool {
	filled, fills := def.FilledParameter()
	program := py.In(codebase).Program
	if !fills {
		return false
	}
	if program.IsOverride(def) || program.IsOverridden(def) || program.ExtendsOutside(def) || program.AnswersAProtocol(def) {
		return false
	}
	at := def.ParameterPosition(filled)
	for _, caller := range program.CallersOf(def) {
		if caller.ArgumentIsReadAfterTheCall(at) {
			return true
		}
	}

	return false
}
