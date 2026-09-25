package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
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
	for _, match := range py.In(codebase).WhereClass().Get() {
		class := py.Node{Match: match}
		for _, field := range class.FieldNames() {
			annotation, ok := class.AttributeAnnotation(field)
			if !ok || class.ResetsToNone(field) {
				continue
			}
			if _, optional := annotation.OptionalOf(); !optional {
				continue
			}
			verdict := program.AttributeFlow(class, field)
			if declaration := class.FieldDeclaration(field); verdict.Assume >= 1 && verdict.Guard == 0 && declaration.Exists() {
				findings = append(findings, declaration.Match)
			}
		}
	}

	return findings
}
