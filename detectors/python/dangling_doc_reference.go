package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// DanglingDocReferenceDetector finds a docstring cross-reference into a package the codebase owns that names nothing there.
type DanglingDocReferenceDetector struct{}

func init() {
	detectors.Register(catalog.Python, DanglingDocReferenceDetector{})
}

func (DanglingDocReferenceDetector) Sin() sins.Sin {
	return pysins.DanglingDocReference{}
}

func (DanglingDocReferenceDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereStatement().
		Where(engine.As(program.HasDanglingDocReference)).
		Get()
}

func (DanglingDocReferenceDetector) CrossFile() {}
