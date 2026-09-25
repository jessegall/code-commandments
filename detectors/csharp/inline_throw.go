package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// InlineThrowDetector finds a `?? throw` buried in the work, handed to a call or read through, rather than a guard of its own.
type InlineThrowDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, InlineThrowDetector{})
}

// Sin is the sin the detector finds.
func (InlineThrowDetector) Sin() sins.Sin {
	return cssins.InlineThrow{}
}

// Find is every place the sin is committed.
func (InlineThrowDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereKind("CoalesceExpression").
		Where(engine.As(cs.Node.IsBuriedThrow)).
		Get()
}
