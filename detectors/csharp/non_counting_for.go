package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// NonCountingForDetector finds a `for` whose step moves no counter but assigns the next item, a `while` written as a `for`.
type NonCountingForDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, NonCountingForDetector{})
}

// Sin is the sin the detector finds.
func (NonCountingForDetector) Sin() sins.Sin {
	return cssins.NonCountingFor{}
}

// Find is every place the sin is committed.
func (NonCountingForDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		Where(engine.As(cs.Node.IsNonCountingFor)).
		Get()
}
