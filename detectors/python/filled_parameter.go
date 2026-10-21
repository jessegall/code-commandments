package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// FilledParameterDetector finds a def returning None that writes an attribute of an object it was handed, whose
// caller then reads what it handed over: the value travels back through the argument, and the signature is silent.
type FilledParameterDetector struct{}

func init() {
	detectors.Register(catalog.Python, FilledParameterDetector{})
}

// Sin is the sin the detector finds.
func (FilledParameterDetector) Sin() sins.Sin {
	return pysins.FilledParameter{}
}

// WholeTree says the verdict needs the callers, and callers live in other files.
func (FilledParameterDetector) WholeTree() {}

// Find is every def that fills a parameter a caller reads after the call.
func (FilledParameterDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereFunction().
		Where(engine.As(func(n py.Node) bool { return fillsWhatACallerThenReads(codebase, n) })).
		Get()
}

// fillsWhatACallerThenReads says whether the def fills one of its parameters and a caller reads the very name it
// handed over, after handing it over. Without that read the def is a command and no business of this rule:
// someone has to be waiting for the change for the signature's silence to cost anything.
func fillsWhatACallerThenReads(codebase *engine.Codebase, def py.Node) bool {
	filled, fills := def.FilledParameter()
	program := py.In(codebase).Program
	if !fills {
		return false
	}
	// A def answering a signature it did not write owns none of it: an override cannot start returning a value,
	// and a Protocol's answerer cannot either, so the silence is not theirs to break.
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
