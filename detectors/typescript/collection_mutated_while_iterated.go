package typescript

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	ts "github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/sins"
	tssins "github.com/jessegall/code-commandments/sins/typescript"
)

// CollectionMutatedWhileIteratedDetector finds a walk that changes the collection it walks.
type CollectionMutatedWhileIteratedDetector struct{}

func init() {
	detectors.Register(catalog.TypeScript, CollectionMutatedWhileIteratedDetector{})
}

// Sin is the sin the detector finds.
func (CollectionMutatedWhileIteratedDetector) Sin() sins.Sin {
	return tssins.CollectionMutatedWhileIterated{}
}

// Find is every change made to the collection a walk around it is walking.
func (CollectionMutatedWhileIteratedDetector) Find(codebase *engine.Codebase) []engine.Match {
	return ts.In(codebase).
		WhereKind("CallExpression").
		Where(engine.As(ts.Node.ChangesTheCollectionItsLoopWalks)).
		Get()
}
