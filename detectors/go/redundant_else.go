package golang

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/golang"
	"github.com/jessegall/code-commandments/sins"
	gosins "github.com/jessegall/code-commandments/sins/go"
)

// RedundantElseDetector finds an `else` after a branch that already left: it says nothing the exit did not, and indents the rest for it.
type RedundantElseDetector struct{}

func init() {
	detectors.Register(catalog.Go, RedundantElseDetector{})
}

// Sin is the sin the detector finds.
func (RedundantElseDetector) Sin() sins.Sin {
	return gosins.RedundantElse{}
}

// Find is every place the sin is committed.
func (RedundantElseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return golang.In(codebase).
		WhereIs(engine.Branch).
		Where(engine.HasRedundantElse).
		Get()
}
