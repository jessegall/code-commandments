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
//
// A METHOD is judged on its mood alone: `panel.Shows()` reads as a claim, and a reader has to stop and work out
// whether the call changes anything. A PROPERTY cannot be read as an order at all, so only the narration itself is
// a sin there: the verb alone, `Holds`, `Spins`. A verb that says what of — `RequiresApproval`, `ReceivesVisitors`
// — is the affirmative phrase a boolean member is asked for, and renaming it buys nothing.
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
		Reject(engine.As(func(n cs.Node) bool { return n.Is("PropertyDeclaration") && !prose.IsBareVerb(n.Name()) })).
		Reject(engine.As(cs.Node.IsInherited)).
		Get()
}
