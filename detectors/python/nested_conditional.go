package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NestedConditionalDetector finds a conditional expression holding another in a branch.
type NestedConditionalDetector struct{}

func init() {
	detectors.Register(catalog.Python, NestedConditionalDetector{})
}

// Sin is the sin the detector finds.
func (NestedConditionalDetector) Sin() sins.Sin {
	return pysins.NestedConditional{}
}

// Find is every place the sin is committed.
func (NestedConditionalDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereKind("IfExp").
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.IsOutermostNestedConditional)).
		Get()
}
