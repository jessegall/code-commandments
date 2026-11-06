package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// BlockedAwaitDetector finds an answer taken out of a task by blocking for it rather than awaiting it.
type BlockedAwaitDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, BlockedAwaitDetector{})
}

func (BlockedAwaitDetector) Sin() sins.Sin {
	return cssins.BlockedAwait{}
}

func (BlockedAwaitDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		Where(engine.As(cs.Node.BlocksOnATask)).
		Reject(engine.As(cs.Node.FillsAnOutsideContract)).
		Get()
}
