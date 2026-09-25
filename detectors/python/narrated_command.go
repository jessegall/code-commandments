package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NarratedCommandDetector finds a command method named in the third person.
type NarratedCommandDetector struct{}

func init() {
	detectors.Register(catalog.Python, NarratedCommandDetector{})
}

// Sin is the sin the detector finds.
func (NarratedCommandDetector) Sin() sins.Sin {
	return pysins.NarratedCommand{}
}

// Find is every place the sin is committed.
func (NarratedCommandDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(program.IsNarratedCommand)).
		Get()
}
