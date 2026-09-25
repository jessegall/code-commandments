package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// PhantomNullableDetector finds an optional field nothing sets back to None that every read assumes is there.
type PhantomNullableDetector struct{}

func init() {
	detectors.Register(catalog.Python, PhantomNullableDetector{})
}

// Sin is the sin the detector finds.
func (PhantomNullableDetector) Sin() sins.Sin {
	return pysins.PhantomNullable{}
}

// Find is every place the sin is committed.
func (PhantomNullableDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program
	var findings []engine.Match
	for _, class := range py.In(codebase).WhereClass().Get() {
		findings = append(findings, matches(program.PhantomNullableFields(py.Node{Match: class}))...)
	}

	return findings
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (PhantomNullableDetector) WholeTree() {}
