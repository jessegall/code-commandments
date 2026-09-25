package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
	"slices"
)

// restatedWords is how many content words a comment needs before it can be said to restate anything.
const restatedWords = 2

// RestatedCommentDetector finds a comment whose every word the statement below it already spells.
type RestatedCommentDetector struct{}

func init() {
	detectors.Register(catalog.Python, RestatedCommentDetector{})
}

// Sin is the sin the detector finds.
func (RestatedCommentDetector) Sin() sins.Sin {
	return pysins.RestatedComment{}
}

// Find is every place the sin is committed.
func (RestatedCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(isInFunction)).
		Reject(engine.As(py.Node.IsFunction)).
		Where(engine.As(func(n py.Node) bool { return len(n.CommentWords()) >= restatedWords })).
		Where(engine.As(restatesCode)).
		Get()
}

// isInFunction says whether the statement sits in a def.
func isInFunction(n py.Node) bool {
	return n.EnclosingFunction().Exists()
}

// restatesCode says whether every content word of the comments above the statement is one the statement spells.
func restatesCode(n py.Node) bool {
	code := n.CodeWords()

	return !slices.ContainsFunc(n.CommentWords(), func(word string) bool { return !slices.Contains(code, word) })
}
