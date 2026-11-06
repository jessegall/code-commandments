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

func init() { detectors.Register(catalog.PHP, DanglingRouteNameDetector{}) }

func (DanglingRouteNameDetector) Sin() sins.Sin { return backendsins.DanglingRouteName{} }

func (DanglingRouteNameDetector) Find(codebase *engine.Codebase) []engine.Match {
	names := laravelnode.RouteNamesOf(codebase)
	if !names.HasAny() {
		return nil
	}

	return php.In(codebase).
		WhereKind("Expr_FuncCall", "Expr_MethodCall", "Expr_StaticCall").
		Where(engine.As(func(n laravelnode.Node) bool { return n.RouteNameReference() != "" })).
		Reject(engine.As(func(n laravelnode.Node) bool { return names.IsRegistered(n.RouteNameReference()) })).
		Get()
}

func (DanglingRouteNameDetector) CrossFile() {}
