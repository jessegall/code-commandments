package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// BlockedAwaitDetector finds a blocking call made inside an async def, where it stops the event loop.
type BlockedAwaitDetector struct{}

func init() {
	detectors.Register(catalog.Python, BlockedAwaitDetector{})
}

// Sin is the sin the detector finds.
func (BlockedAwaitDetector) Sin() sins.Sin {
	return pysins.BlockedAwait{}
}

// Find is every blocking call made on the event loop's own thread.
func (BlockedAwaitDetector) Find(codebase *engine.Codebase) []engine.Match {
	return py.In(codebase).
		WhereCall().
		Where(engine.As(py.Node.BlocksTheEventLoop)).
		Get()
}
