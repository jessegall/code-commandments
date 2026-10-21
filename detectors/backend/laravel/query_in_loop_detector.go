package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	laravelnode "github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

// QueryInLoopDetector finds a query run inside a loop: one per turn of it, which is the N+1.
type QueryInLoopDetector struct{}

func init() { detectors.Register(catalog.PHP, QueryInLoopDetector{}) }

// Sin is the sin the detector finds.
func (QueryInLoopDetector) Sin() sins.Sin { return backendsins.QueryInLoop{} }

// Find is every call that goes to the database from inside a loop.
func (QueryInLoopDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(laravelnode.Node.RunsAQueryInALoop)).
		Get()
}
