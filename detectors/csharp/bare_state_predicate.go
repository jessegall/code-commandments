package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/prose"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// BareStatePredicateDetector finds a `bool` about the object alone named as a narration, `Ships`, where a question, `IsShipped`, is meant.
type BareStatePredicateDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, BareStatePredicateDetector{})
}

// Sin is the sin the detector finds.
func (BareStatePredicateDetector) Sin() sins.Sin {
	return cssins.BareStatePredicate{}
}

// Find is every place the sin is committed.
func (BareStatePredicateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		Where(engine.As(cs.Node.IsStatePredicate)).
		Where(engine.As(func(n cs.Node) bool { return prose.IsThirdPerson(n.Name()) })).
		Reject(engine.As(func(n cs.Node) bool { return prose.ReadsAsQuestion(n.Name()) })).
		Reject(engine.As(cs.Node.IsInherited)).
		Get()
}
