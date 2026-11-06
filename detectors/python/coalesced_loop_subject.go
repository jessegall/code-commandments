package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// CoalescedLoopSubjectDetector finds a loop over a parameter's collection that falls back to an empty one: `for x in order.lines or []`.
type CoalescedLoopSubjectDetector struct{}

func init() {
	detectors.Register(catalog.Python, CoalescedLoopSubjectDetector{})
}

func (CoalescedLoopSubjectDetector) Sin() sins.Sin {
	return pysins.CoalescedLoopSubject{}
}

func (CoalescedLoopSubjectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereExpression().
		Where(engine.As(py.Node.IsLoopSubject)).
		Where(engine.As(py.Node.FallsBackToEmptyCollection)).
		Where(engine.As(py.Node.FallbackReachesIntoParameter)).
		Get()
}
