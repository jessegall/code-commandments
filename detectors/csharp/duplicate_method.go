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

func (DuplicateMethodDetector) Sin() sins.Sin {
	return cssins.DuplicateMethod{}
}

func (d DuplicateMethodDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

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

func (DuplicateMethodDetector) Decide(candidates []detectors.Candidate) []int {
	return recurringAt(candidates, 2)
}

func (DuplicateMethodDetector) GroupKey(match engine.Match) (string, bool) {
	return bodyHash(match)
}

func bodyHash(match engine.Match) (string, bool) {
	hash := cs.Node{Match: match}.BodyHash()

	return hash, hash != ""
}

func (DuplicateMethodDetector) CrossFile() {}
