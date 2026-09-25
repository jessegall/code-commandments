package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// ArrayReturnBagDetector finds a dictionary built with two or more string keys written in the source and handed back: an object with no type, outside an override and the tests.
type ArrayReturnBagDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, ArrayReturnBagDetector{})
}

// Sin is the sin the detector finds.
func (ArrayReturnBagDetector) Sin() sins.Sin {
	return cssins.ArrayReturnBag{}
}

// Find is every place the sin is committed.
func (ArrayReturnBagDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereExpression().
		Where(engine.As(func(n cs.Node) bool { return distinct(n.LiteralKeys()) >= 2 })).
		Where(engine.As(cs.Node.IsReturned)).
		Reject(engine.As(cs.Node.IsWithinOverride)).
		Reject(engine.As(cs.Node.IsInTest)).
		Get()
}

// distinct is how many different words there are.
func distinct(words []string) int {
	seen := map[string]bool{}
	for _, word := range words {
		seen[word] = true
	}

	return len(seen)
}
