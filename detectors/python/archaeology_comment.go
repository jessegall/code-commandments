package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/prose"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
	"slices"
)

// ArchaeologyCommentDetector finds prose written for a statement that narrates the code's past instead of its present.
type ArchaeologyCommentDetector struct{}

func init() {
	detectors.Register(catalog.Python, ArchaeologyCommentDetector{})
}

// Sin is the sin the detector finds.
func (ArchaeologyCommentDetector) Sin() sins.Sin {
	return pysins.ArchaeologyComment{}
}

// Find is every place the sin is committed.
func (ArchaeologyCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(proseCommits(prose.NarratesHistory))).
		Get()
}

// proseCommits is a check that any text of the prose written for a statement commits the sin the judge finds,
// its Sphinx version notes set aside.
func proseCommits(sinful func(string) bool) func(py.Node) bool {
	return func(n py.Node) bool {
		return slices.ContainsFunc(n.Prose(), func(text string) bool { return sinful(py.WithoutVersionNotes(text)) })
	}
}
