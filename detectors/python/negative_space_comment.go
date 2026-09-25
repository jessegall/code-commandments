package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/prose"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NegativeSpaceCommentDetector finds prose written for a statement that defends it against a reading nobody made.
type NegativeSpaceCommentDetector struct{}

func init() {
	detectors.Register(catalog.Python, NegativeSpaceCommentDetector{})
}

// Sin is the sin the detector finds.
func (NegativeSpaceCommentDetector) Sin() sins.Sin {
	return pysins.NegativeSpaceComment{}
}

// Find is every place the sin is committed.
func (NegativeSpaceCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(proseCommits(prose.DefendsAgainstStrawman))).
		Get()
}
