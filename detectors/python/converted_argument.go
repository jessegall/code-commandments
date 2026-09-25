package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ConvertedArgumentDetector finds a scalar parameter most of whose callers build it by one and the same conversion:
// the parameter should take what they convert.
type ConvertedArgumentDetector struct{}

func init() {
	detectors.Register(catalog.Python, ConvertedArgumentDetector{})
}

// Sin is the sin the detector finds.
func (ConvertedArgumentDetector) Sin() sins.Sin {
	return pysins.ConvertedArgument{}
}

// Find is every place the sin is committed.
func (ConvertedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return matches(py.In(codebase).Program.ConvertedArgumentCalls())
}

// matches is the nodes as the matches a finding reports.
func matches(nodes []py.Node) []engine.Match {
	found := make([]engine.Match, len(nodes))
	for at, node := range nodes {
		found[at] = node.Match
	}

	return found
}

// GroupKey groups a finding with the calls converting the same argument of the same def the same way.
func (ConvertedArgumentDetector) GroupKey(match engine.Match) (string, bool) {
	return py.In(match.Codebase()).Program.ConversionKey(py.Node{Match: match})
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (ConvertedArgumentDetector) WholeTree() {}
