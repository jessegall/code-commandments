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
func (DuplicateMethodDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := cs.In(codebase).
		WhereFunction().
		Where(engine.As(func(n cs.Node) bool { return n.BodyWeight() >= 12 })).
		Reject(engine.As(func(n cs.Node) bool { return n.Is("ConstructorDeclaration") })).
		Get()
	var found []engine.Match
	for _, bucket := range engine.RecurringBuckets(candidates, bodyHash, 2) {
		found = append(found, bucket...)
	}

	return found
}

// bodyHash is the fingerprint of the body the function runs.
func bodyHash(match engine.Match) (string, bool) {
	hash := cs.Node{Match: match}.BodyHash()

	return hash, hash != ""
}
