package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// RedundantElseDetector finds an else after an if whose body always leaves.
type RedundantElseDetector struct{}

func init() {
	detectors.Register(catalog.Python, RedundantElseDetector{})
}

func (RedundantElseDetector) Sin() sins.Sin {
	return pysins.RedundantElse{}
}

func (RedundantElseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.HasRedundantElse).
		Get()
}
