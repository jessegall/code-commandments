package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// ladderRungs is how many rungs make a ladder.
const ladderRungs = 4

// SubjectLadderDetector finds an if and its elifs comparing one subject to constant after constant: a dispatch written as a ladder.
type SubjectLadderDetector struct{}

func init() {
	detectors.Register(catalog.Python, SubjectLadderDetector{})
}

// Sin is the sin the detector finds.
func (SubjectLadderDetector) Sin() sins.Sin {
	return pysins.SubjectLadder{}
}

// Find is every place the sin is committed.
func (SubjectLadderDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(func(n py.Node) bool { return n.SubjectLadderLength() >= ladderRungs })).
		Get()
}
