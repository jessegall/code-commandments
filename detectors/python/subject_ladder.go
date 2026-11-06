package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

const ladderRungs = 4

// SubjectLadderDetector finds an if and its elifs comparing one subject to constant after constant: a dispatch written as a ladder.
type SubjectLadderDetector struct{}

func init() {
	detectors.Register(catalog.Python, SubjectLadderDetector{})
}

func (SubjectLadderDetector) Sin() sins.Sin {
	return pysins.SubjectLadder{}
}

func (SubjectLadderDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(func(held engine.Match) bool { return engine.SubjectLadderLength(held) >= ladderRungs }).
		Get()
}
