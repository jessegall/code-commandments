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

func (CollectionMutatedWhileIteratedDetector) Sin() sins.Sin {
	return tssins.CollectionMutatedWhileIterated{}
}

func (CollectionMutatedWhileIteratedDetector) Find(codebase *engine.Codebase) []engine.Match {
	return ts.In(codebase).
		WhereKind("CallExpression").
		Where(engine.As(ts.Node.ChangesTheCollectionItsLoopWalks)).
		Get()
}
