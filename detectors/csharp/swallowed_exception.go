package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// SwallowedExceptionDetector finds a `catch` of everything that makes the failure vanish: an empty body, a `continue`, or a return of nothing.
type SwallowedExceptionDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, SwallowedExceptionDetector{})
}

func (SwallowedExceptionDetector) Sin() sins.Sin {
	return cssins.SwallowedException{}
}

func (SwallowedExceptionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereKind("CatchClause").
		Where(engine.As(cs.Node.IsBroadCatch)).
		Where(engine.As(cs.Node.Swallows)).
		Get()
}
