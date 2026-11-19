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

// HardCodedRoutePathDetector finds a path written by hand that a named route already declares.
type HardCodedRoutePathDetector struct{}

func init() { detectors.Register(catalog.PHP, HardCodedRoutePathDetector{}) }

func (HardCodedRoutePathDetector) Sin() sins.Sin { return backendsins.HardCodedRoutePath{} }

func (HardCodedRoutePathDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(laravelnode.Node.SpellsANamedRoutePath)).
		Reject(engine.As(laravelnode.Node.IsRouterArgument)).
		Reject(engine.Match.IsTest).
		Get()
}

func (HardCodedRoutePathDetector) CrossFile() {}
