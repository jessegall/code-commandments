package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

const nestingLimit = 3

// DeepNestingDetector finds a choice nested past the depth a def can be read at.
type DeepNestingDetector struct{}

func init() {
	detectors.Register(catalog.Python, DeepNestingDetector{})
}

func (DeepNestingDetector) Sin() sins.Sin {
	return pysins.DeepNesting{}
}

func (DeepNestingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(py.Node.IsBranchingConstruct)).
		Reject(engine.As(py.Node.IsElif)).
		Where(engine.As(func(n py.Node) bool { return n.BranchingDepth() == nestingLimit })).
		Get()
}
