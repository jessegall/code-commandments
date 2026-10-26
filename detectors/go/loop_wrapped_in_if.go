package golang

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/golang"
	"github.com/jessegall/code-commandments/sins"
	gosins "github.com/jessegall/code-commandments/sins/go"
)

// LoopWrappedInIfDetector finds an `if` that is a loop's whole body, burying its work a level deep where `continue` would keep it flat.
type LoopWrappedInIfDetector struct{}

func init() {
	detectors.Register(catalog.Go, LoopWrappedInIfDetector{})
}

// Sin is the sin the detector finds.
func (LoopWrappedInIfDetector) Sin() sins.Sin {
	return gosins.LoopWrappedInIf{}
}

// Find is every place the sin is committed.
func (LoopWrappedInIfDetector) Find(codebase *engine.Codebase) []engine.Match {
	return golang.In(codebase).
		WhereIs(engine.Branch).
		Where(engine.IsSoleLoopBodyGuard).
		Get()
}
