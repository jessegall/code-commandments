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

func (RedundantElseDetector) Sin() sins.Sin {
	return cssins.RedundantElse{}
}

func (RedundantElseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereStatement().
		Where(engine.HasRedundantElse).
		Get()
}
