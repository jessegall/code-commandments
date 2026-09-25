package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// LoopWrappedInIfDetector finds an `if` that is a loop's whole body, burying its work a level deep where `if (!…) continue;` would keep it flat.
type LoopWrappedInIfDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, LoopWrappedInIfDetector{})
}

// Sin is the sin the detector finds.
func (LoopWrappedInIfDetector) Sin() sins.Sin {
	return cssins.LoopWrappedInIf{}
}

// Find is every place the sin is committed.
func (LoopWrappedInIfDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereStatement().
		Where(engine.As(cs.Node.IsSoleLoopBodyGuard)).
		Get()
}
