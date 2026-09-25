package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ConditionalArraySpreadDetector finds an array spread or merged in only when a condition holds, [] otherwise.
type ConditionalArraySpreadDetector struct{}

func init() { detectors.Register(catalog.Backend, ConditionalArraySpreadDetector{}) }

// Sin is the sin the detector finds.
func (ConditionalArraySpreadDetector) Sin() sins.Sin { return backendsins.ConditionalArraySpread{} }

// Find is every ternary between an empty and a filled array that is spread or merged.
func (ConditionalArraySpreadDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsConditionalArraySpread)).
		Get()
}
