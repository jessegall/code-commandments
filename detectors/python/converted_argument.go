package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ConvertedArgumentDetector finds a scalar parameter most of whose callers build it by one and the same conversion: the parameter should take what they convert.
type ConvertedArgumentDetector struct{}

func init() {
	detectors.Register(catalog.Python, ConvertedArgumentDetector{})
}

func (ConvertedArgumentDetector) Sin() sins.Sin {
	return pysins.ConvertedArgument{}
}

func (ConvertedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return matches(py.In(codebase).Program.ConvertedArgumentCalls())
}

func matches(nodes []py.Node) []engine.Match {
	found := make([]engine.Match, len(nodes))
	for at, node := range nodes {
		found[at] = node.Match
	}

	return found
}

func (ConvertedArgumentDetector) GroupKey(match engine.Match) (string, bool) {
	return py.In(match.Codebase()).Program.ConversionKey(py.Node{Match: match})
}

func (ConvertedArgumentDetector) CrossFile() {}
