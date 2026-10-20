package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// DuplicateMethodDetector finds two or more functions running the same body of twelve nodes or more, constructors aside.
type DuplicateMethodDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, DuplicateMethodDetector{})
}

// Sin is the sin the detector finds.
func (DuplicateMethodDetector) Sin() sins.Sin {
	return cssins.DuplicateMethod{}
}

// Find is every place the sin is committed.
func (d DuplicateMethodDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

// Candidates is every function heavy enough to be worth sharing, keyed by the body it runs.
func (d DuplicateMethodDetector) Candidates(codebase *engine.Codebase) []detectors.Candidate {
	candidates := cs.In(codebase).
		WhereFunction().
		Where(engine.As(func(n cs.Node) bool { return n.BodyWeight() >= 12 })).
		Reject(engine.As(func(n cs.Node) bool { return n.Is("ConstructorDeclaration") })).
		Reject(engine.As(cs.Node.IsSoleReturnExpression)).
		Reject(engine.As(cs.Node.IsSoleExpressionStatement)).
		Reject(engine.Match.IsTest).
		Get()

	return keyedBy(candidates, d.GroupKey)
}

// Decide is every function whose body another runs too. A body that is one call or one returned value is left
// alone: the mechanism it would share is the callee, which already exists, and hoisting it would leave a method
// whose whole body calls a method. A framework that binds one method per type, as Wolverine binds a handler's
// Validate, makes that one line unavoidable.
func (DuplicateMethodDetector) Decide(candidates []detectors.Candidate) []int {
	return recurringAt(candidates, 2)
}

// GroupKey is the group a function recurs in: the body it runs.
func (DuplicateMethodDetector) GroupKey(match engine.Match) (string, bool) {
	return bodyHash(match)
}

// bodyHash is the fingerprint of the body the function runs.
func bodyHash(match engine.Match) (string, bool) {
	hash := cs.Node{Match: match}.BodyHash()

	return hash, hash != ""
}
