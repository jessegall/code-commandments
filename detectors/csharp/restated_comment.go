package csharp

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// RestatedCommentDetector finds a comment above a statement whose every content word the statement already spells.
type RestatedCommentDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, RestatedCommentDetector{})
}

// Sin is the sin the detector finds.
func (RestatedCommentDetector) Sin() sins.Sin {
	return cssins.RestatedComment{}
}

// Find is every place the sin is committed.
func (RestatedCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereStatement().
		Reject(engine.As(func(n cs.Node) bool { return n.Is("Block") })).
		Where(engine.As(func(n cs.Node) bool { return len(n.CommentWords()) >= 2 })).
		Where(engine.As(func(n cs.Node) bool {
			code := n.CodeWords()

			return !slices.ContainsFunc(n.CommentWords(), func(word string) bool { return !slices.Contains(code, word) })
		})).
		Get()
}
