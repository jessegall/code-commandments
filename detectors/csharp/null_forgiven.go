package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// NullForgivenDetector finds a `!` silencing the compiler over a value declared nullable, outside the tests.
type NullForgivenDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, NullForgivenDetector{})
}

// Sin is the sin the detector finds.
func (NullForgivenDetector) Sin() sins.Sin {
	return cssins.NullForgiven{}
}

// Find is every place the sin is committed.
func (NullForgivenDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereExpression().
		Where(engine.As(cs.Node.ForgivesNull)).
		Reject(engine.As(cs.Node.IsInTest)).
		Get()
}
