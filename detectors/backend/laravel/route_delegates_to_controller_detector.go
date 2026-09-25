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

// RouteDelegatesToControllerDetector finds a route action that only hands its request on to another route action: two entry points to one operation.
type RouteDelegatesToControllerDetector struct{}

func init() { detectors.Register(catalog.Backend, RouteDelegatesToControllerDetector{}) }

// Sin is the sin the detector finds.
func (RouteDelegatesToControllerDetector) Sin() sins.Sin {
	return backendsins.RouteDelegatesToController{}
}

// Find is every route action that delegates to another route action.
func (RouteDelegatesToControllerDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Where(engine.As(laravelnode.Node.IsRouteAction)).
		Where(engine.As(laravelnode.Node.DelegatesToRouteAction)).
		Get()
}
