package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NarratedCommandDetector finds a command method named in the third person.
type NarratedCommandDetector struct{}

func init() {
	detectors.Register(catalog.Python, NarratedCommandDetector{})
}

func (NarratedCommandDetector) Sin() sins.Sin {
	return pysins.NarratedCommand{}
}

func (NarratedCommandDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(program.IsNarratedCommand)).
		Get()
}
