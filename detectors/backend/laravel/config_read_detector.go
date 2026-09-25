package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

// ConfigReadDetector finds config() read inside a class, where the value belongs injected as a typed dependency.
type ConfigReadDetector struct{}

func init() { detectors.Register(catalog.Backend, ConfigReadDetector{}) }

// Sin is the sin the detector finds.
func (ConfigReadDetector) Sin() sins.Sin { return backendsins.ConfigRead{} }

// Exemptions excuses the composition root, where config is wired into typed objects.
func (ConfigReadDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.CompositionRoot, By: []packages.By{packages.EnclosingClass}}}
}

// Find is every config() call written in a class.
func (d ConfigReadDetector) Find(codebase *engine.Codebase) []engine.Match {
	return packages.Exempt(codebase, d, php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.CallsFunction("config") })).
		Where(engine.As(php.Node.IsEnclosedInClass)).
		Get())
}
