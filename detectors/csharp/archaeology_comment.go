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

// ArchaeologyCommentDetector finds a comment narrating the code's history, read by the one shared reading of prose.
type ArchaeologyCommentDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, ArchaeologyCommentDetector{})
}

// Sin is the sin the detector finds.
func (ArchaeologyCommentDetector) Sin() sins.Sin {
	return cssins.ArchaeologyComment{}
}

// Find is every place the sin is committed.
func (ArchaeologyCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).WhereComment(func(comment cs.Comment) bool { return prose.NarratesHistory(comment.Prose()) })
}
