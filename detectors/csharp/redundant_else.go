package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// RedundantElseDetector finds an `else` after a branch that already left: it says nothing the exit did not, and indents the rest for it.
type RedundantElseDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, RedundantElseDetector{})
}

// Sin is the sin the detector finds.
func (RedundantElseDetector) Sin() sins.Sin {
	return cssins.RedundantElse{}
}

// Find is every place the sin is committed.
func (RedundantElseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereStatement().
		Where(engine.As(cs.Node.HasRedundantElse)).
		Get()
}
