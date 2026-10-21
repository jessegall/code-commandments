package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// CollectionMutatedWhileIteratedDetector finds a `foreach` body that changes, in place, the collection its own
// loop walks: the enumerator then reads a collection that has moved under it.
type CollectionMutatedWhileIteratedDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, CollectionMutatedWhileIteratedDetector{})
}

// Sin is the sin the detector finds.
func (CollectionMutatedWhileIteratedDetector) Sin() sins.Sin {
	return cssins.CollectionMutatedWhileIterated{}
}

// Find is every change in place to the collection a foreach around it is walking.
func (CollectionMutatedWhileIteratedDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereCall().
		Where(engine.As(cs.Node.ChangesTheCollectionItsLoopWalks)).
		Get()
}
