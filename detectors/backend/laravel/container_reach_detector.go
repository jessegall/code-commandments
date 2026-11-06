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

// ContainerReachDetector finds a class resolving a dependency from the container by hand, where it belongs injected through the constructor.
type ContainerReachDetector struct{}

func init() { detectors.Register(catalog.PHP, ContainerReachDetector{}) }

func (ContainerReachDetector) Sin() sins.Sin { return backendsins.ContainerReach{} }

func (ContainerReachDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.NoContainer, By: []packages.By{packages.EnclosingClass}}}
}

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

func (ContainerReachDetector) CrossFile() {}
