package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// CollectionMutatedWhileIteratedDetector finds a loop body that changes, in place, the collection its own loop
// walks: the iterator then reads a sequence that has moved under it.
type CollectionMutatedWhileIteratedDetector struct{}

func init() {
	detectors.Register(catalog.Python, CollectionMutatedWhileIteratedDetector{})
}

// Sin is the sin the detector finds.
func (CollectionMutatedWhileIteratedDetector) Sin() sins.Sin {
	return pysins.CollectionMutatedWhileIterated{}
}

// Find is every change in place to the collection a loop around it is walking.
func (CollectionMutatedWhileIteratedDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		Where(engine.As(py.Node.ChangesTheCollectionItsLoopWalks)).
		Get()
}
