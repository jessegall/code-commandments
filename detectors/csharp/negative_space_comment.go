package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/prose"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// NegativeSpaceCommentDetector finds a comment defending the code against a strawman: what it does not do, or what nobody asked.
type NegativeSpaceCommentDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, NegativeSpaceCommentDetector{})
}

// Sin is the sin the detector finds.
func (NegativeSpaceCommentDetector) Sin() sins.Sin {
	return cssins.NegativeSpaceComment{}
}

// Find is every place the sin is committed.
func (NegativeSpaceCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).WhereComment(func(comment cs.Comment) bool { return prose.DefendsAgainstStrawman(comment.Prose()) })
}
