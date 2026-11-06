package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// KeyedLookupEnvyDetector finds a method fetching a fact about its one owned parameter through a collaborator, keyed by that parameter.
type KeyedLookupEnvyDetector struct{}

func init() {
	detectors.Register(catalog.Python, KeyedLookupEnvyDetector{})
}

func (KeyedLookupEnvyDetector) Sin() sins.Sin {
	return pysins.KeyedLookupEnvy{}
}

func (KeyedLookupEnvyDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(program.IsLookupEnvious)).
		Get()
}

func (KeyedLookupEnvyDetector) CrossFile() {}
