package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// NearDuplicateMethodDetector finds two or more functions of twenty nodes or more sharing one skeleton, whatever their locals are called and whichever constants they use, where neither is the other's exact copy.
type NearDuplicateMethodDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, NearDuplicateMethodDetector{})
}

// Sin is the sin the detector finds.
func (NearDuplicateMethodDetector) Sin() sins.Sin {
	return cssins.NearDuplicateMethod{}
}

// Find is every place the sin is committed.
func (d NearDuplicateMethodDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

// Candidates is every function heavy enough to be worth sharing and free to change, keyed by the shape of its body
// and by the body it runs exactly.
func (d NearDuplicateMethodDetector) Candidates(codebase *engine.Codebase) []detectors.Candidate {
	matches := cs.In(codebase).
		WhereFunction().
		Where(engine.As(func(n cs.Node) bool { return n.BodyWeight() >= 20 })).
		Reject(engine.As(func(n cs.Node) bool { return n.Is("ConstructorDeclaration") })).
		Reject(engine.As(cs.Node.IsSoleReturnExpression)).
		Reject(engine.As(cs.Node.IsSoleExpressionStatement)).
		Reject(engine.As(cs.Node.IsLiteralLookup)).
		Reject(engine.As(cs.Node.IsStub)).
		Reject(engine.As(cs.Node.IsInherited)).
		Get()
	candidates := keyedBy(matches, d.GroupKey)
	for at, match := range matches {
		record := candidates[at].Record.(keyed)
		record.exact, _ = bodyHash(match)
		candidates[at].Record = record
	}

	return candidates
}

// Decide is every function alike in shape to another, yet the only one running its exact body.
func (NearDuplicateMethodDetector) Decide(candidates []detectors.Candidate) []int {
	return engine.NearCopyPositions(len(candidates), keyOf(candidates), func(at int) (string, bool) {
		exact := candidates[at].Record.(keyed).exact

		return exact, exact != ""
	})
}

// GroupKey is the group a function recurs in: the skeleton of its body, whatever its locals and constants.
func (NearDuplicateMethodDetector) GroupKey(match engine.Match) (string, bool) {
	hash := cs.Node{Match: match}.ShapeHash()

	return hash, hash != ""
}
