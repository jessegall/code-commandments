package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// FeatureEnvyDetector finds a method reaching through one other object it was handed more than its own state, looping or writing it: behaviour exiled from its data.
type FeatureEnvyDetector struct{}

func init() {
	detectors.Register(catalog.Python, FeatureEnvyDetector{})
}

// Sin is the sin the detector finds.
func (FeatureEnvyDetector) Sin() sins.Sin {
	return pysins.FeatureEnvy{}
}

// Find is every place the sin is committed.
func (FeatureEnvyDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program

	return py.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(func(n py.Node) bool { _, envies := program.EnviedParameter(n); return envies })).
		Get()
}
