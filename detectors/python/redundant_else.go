package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// RedundantElseDetector finds an else after an if whose body always leaves.
type RedundantElseDetector struct{}

func init() {
	detectors.Register(catalog.Python, RedundantElseDetector{})
}

// Sin is the sin the detector finds.
func (RedundantElseDetector) Sin() sins.Sin {
	return pysins.RedundantElse{}
}

// Find is every place the sin is committed.
func (RedundantElseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(py.Node.HasRedundantElse)).
		Get()
}
