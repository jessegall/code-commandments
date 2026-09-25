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

// DanglingRouteNameDetector finds a route name looked up that no route registers.
type DanglingRouteNameDetector struct{}

func init() { detectors.Register(catalog.Backend, DanglingRouteNameDetector{}) }

// Sin is the sin the detector finds.
func (DanglingRouteNameDetector) Sin() sins.Sin { return backendsins.DanglingRouteName{} }

// Find is every lookup of a route name no route registers, in a project that names its routes at all.
func (DanglingRouteNameDetector) Find(codebase *engine.Codebase) []engine.Match {
	names := laravelnode.RouteNamesOf(codebase)
	if !names.HasAny() {
		return nil
	}

	return php.In(codebase).
		Where(engine.As(func(n laravelnode.Node) bool { return n.RouteNameReference() != "" })).
		Reject(engine.As(func(n laravelnode.Node) bool { return names.IsRegistered(n.RouteNameReference()) })).
		Get()
}
