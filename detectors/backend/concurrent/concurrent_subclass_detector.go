package concurrent

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	concurrentnode "github.com/jessegall/code-commandments/engine/php/concurrent"
	"github.com/jessegall/code-commandments/sins"
	concurrentsins "github.com/jessegall/code-commandments/sins/backend/concurrent"
)

// ConcurrentSubclassDetector finds a class extending Concurrent, where a plain class behind a ::for() factory belongs.
type ConcurrentSubclassDetector struct{}

func init() { detectors.Register(catalog.Backend, ConcurrentSubclassDetector{}) }

// Sin is the sin the detector finds.
func (ConcurrentSubclassDetector) Sin() sins.Sin { return concurrentsins.ConcurrentSubclass{} }

// Find is every class that extends Concurrent.
func (ConcurrentSubclassDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(concurrentnode.Node.ExtendsConcurrent)).
		Get()
}
