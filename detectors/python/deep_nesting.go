package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// nestingLimit is how many choices a construct may already sit inside; the one past it is flagged.
const nestingLimit = 3

// DeepNestingDetector finds a choice nested past the depth a def can be read at.
type DeepNestingDetector struct{}

func init() {
	detectors.Register(catalog.Python, DeepNestingDetector{})
}

// Sin is the sin the detector finds.
func (DeepNestingDetector) Sin() sins.Sin {
	return pysins.DeepNesting{}
}

// Find is every place the sin is committed.
func (DeepNestingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(py.Node.IsBranchingConstruct)).
		Reject(engine.As(py.Node.IsElif)).
		Where(engine.As(func(n py.Node) bool { return n.BranchingDepth() == nestingLimit })).
		Get()
}
