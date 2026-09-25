package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// CeremonyDocblockDetector finds a doc comment that only restates the signature it documents.
type CeremonyDocblockDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, CeremonyDocblockDetector{})
}

// Sin is the sin the detector finds.
func (CeremonyDocblockDetector) Sin() sins.Sin {
	return cssins.CeremonyDocblock{}
}

// Find is every place the sin is committed.
func (CeremonyDocblockDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).WhereComment(func(comment cs.Comment) bool {
		return comment.IsDoc() && comment.RestatesOnly(comment.Documented().SignatureWords())
	})
}
