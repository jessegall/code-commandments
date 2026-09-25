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

// DanglingDocReferenceDetector finds a doc reference to code the program does not have.
type DanglingDocReferenceDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, DanglingDocReferenceDetector{})
}

// Sin is the sin the detector finds.
func (DanglingDocReferenceDetector) Sin() sins.Sin {
	return cssins.DanglingDocReference{}
}

// Find is every place the sin is committed.
func (DanglingDocReferenceDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).WhereComment(func(comment cs.Comment) bool { return slices.ContainsFunc(comment.Refs, cs.IsDangling) })
}
