package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// BloatedDocblockDetector finds a type's doc comment running to two or more paragraphs of description.
type BloatedDocblockDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, BloatedDocblockDetector{})
}

// Sin is the sin the detector finds.
func (BloatedDocblockDetector) Sin() sins.Sin {
	return cssins.BloatedDocblock{}
}

// Find is every place the sin is committed.
func (BloatedDocblockDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).WhereComment(func(comment cs.Comment) bool {
		return comment.IsDoc() && comment.Documented().IsTypeDeclaration() && comment.Paragraphs() >= 2
	})
}
