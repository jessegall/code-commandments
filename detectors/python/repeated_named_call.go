package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

const namedCallThreshold = 2

// RepeatedNamedCallDetector finds the same keyword call, building the same shapes, made to a def taking **kwargs at two or more sites: a missing method.
type RepeatedNamedCallDetector struct{}

func init() {
	detectors.Register(catalog.Python, RepeatedNamedCallDetector{})
}

func (RepeatedNamedCallDetector) Sin() sins.Sin {
	return pysins.RepeatedNamedCall{}
}

func (RepeatedNamedCallDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program
	calls := py.In(codebase).WhereCall().Where(engine.As(py.Node.IsEvaluated)).Get()

	return flatten(engine.RecurringBuckets(calls, func(match engine.Match) (string, bool) { return program.NamedCallKey(py.Node{Match: match}) }, namedCallThreshold))
}

func (RepeatedNamedCallDetector) GroupKey(match engine.Match) (string, bool) {
	return py.In(match.Codebase()).Program.NamedCallKey(py.Node{Match: match})
}

func (RepeatedNamedCallDetector) CrossFile() {}
