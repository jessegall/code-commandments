package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	laravelnode "github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

// ContainerReachDetector finds a class resolving a dependency from the container by hand, where it belongs injected
// through the constructor.
type ContainerReachDetector struct{}

func init() { detectors.Register(catalog.Backend, ContainerReachDetector{}) }

// Sin is the sin the detector finds.
func (ContainerReachDetector) Sin() sins.Sin { return backendsins.ContainerReach{} }

// Exemptions excuses a class the framework builds without the container, which has nothing to inject into.
func (ContainerReachDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.NoContainer, By: []packages.By{packages.EnclosingClass}}}
}

// Find is every app() or resolve() of a named class, outside enums and a class resolving itself, in a class the
// container builds.
func (d ContainerReachDetector) Find(codebase *engine.Codebase) []engine.Match {
	return packages.Exempt(codebase, d, php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.CallsFunction("app") || n.CallsFunction("resolve") })).
		Reject(engine.As(php.Node.IsInEnum)).
		Where(engine.As(php.Node.FirstArgIsClassLiteral)).
		Reject(engine.As(php.Node.IsEnclosingClassResolution)).
		Where(func(n engine.Match) bool { return laravelnode.ContainerResolves(codebase, php.EnclosingClassName(n)) }).
		Reject(engine.Match.IsTest).
		Get())
}
