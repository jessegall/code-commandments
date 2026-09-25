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

// Sin is the sin the detector finds.
func (KeyedLookupEnvyDetector) Sin() sins.Sin {
	return pysins.KeyedLookupEnvy{}
}

// Find is every place the sin is committed.
func (KeyedLookupEnvyDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(program.IsLookupEnvious)).
		Get()
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (KeyedLookupEnvyDetector) CrossFile() {}
