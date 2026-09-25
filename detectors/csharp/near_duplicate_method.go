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
func (NearDuplicateMethodDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := cs.In(codebase).
		WhereFunction().
		Where(engine.As(func(n cs.Node) bool { return n.BodyWeight() >= 20 })).
		Reject(engine.As(func(n cs.Node) bool { return n.Is("ConstructorDeclaration") })).
		Reject(engine.As(cs.Node.IsSoleReturnExpression)).
		Reject(engine.As(cs.Node.IsSoleExpressionStatement)).
		Reject(engine.As(cs.Node.IsLiteralLookup)).
		Reject(engine.As(cs.Node.IsStub)).
		Reject(engine.As(cs.Node.IsInherited)).
		Get()
	return engine.NearCopies(candidates, NearDuplicateMethodDetector{}.GroupKey, bodyHash)
}

// GroupKey is the group a function recurs in: the skeleton of its body, whatever its locals and constants.
func (NearDuplicateMethodDetector) GroupKey(match engine.Match) (string, bool) {
	hash := cs.Node{Match: match}.ShapeHash()

	return hash, hash != ""
}
