package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

const restatedWords = 2

// RestatedCommentDetector finds a comment whose every word the statement below it already spells.
type RestatedCommentDetector struct{}

func init() {
	detectors.Register(catalog.Python, RestatedCommentDetector{})
}

func (RestatedCommentDetector) Sin() sins.Sin {
	return pysins.RestatedComment{}
}

func (RestatedCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereStatement().
		Where(engine.As(isInFunction)).
		Reject(engine.As(py.Node.IsFunction)).
		Where(engine.As(func(n py.Node) bool { return len(n.CommentWords()) >= restatedWords })).
		Where(engine.As(py.Node.RestatesCode)).
		Get()
}

func isInFunction(n py.Node) bool {
	return n.EnclosingFunction().Exists()
}
