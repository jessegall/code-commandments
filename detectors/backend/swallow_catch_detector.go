package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// SwallowCatchDetector finds a catch that drops the failure it caught.
type SwallowCatchDetector struct{}

func init() { detectors.Register(catalog.Backend, SwallowCatchDetector{}) }

// Sin is the sin the detector finds.
func (SwallowCatchDetector) Sin() sins.Sin { return backendsins.SwallowCatch{} }

// Exemptions lets a package declare its control signals, whose silent catch is the point.
func (SwallowCatchDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.ControlSignal}}
}

// Find is every swallowing catch, save one that catches only control signals.
func (SwallowCatchDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(php.Node.IsSwallowedCatch)).
		Reject(engine.As(func(n php.Node) bool { return catchesOnlyControlSignals(codebase, n) })).
		Get()
}

func catchesOnlyControlSignals(codebase *engine.Codebase, catch php.Node) bool {
	types := catch.CaughtTypes()
	for _, caught := range types {
		if !packages.Excuses(codebase, packages.ControlSignal, caught, "") {
			return false
		}
	}

	return len(types) > 0
}
