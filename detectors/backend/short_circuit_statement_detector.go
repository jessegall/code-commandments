package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ShortCircuitStatementDetector finds && or || used as a statement to run its right side conditionally.
type ShortCircuitStatementDetector struct{}

func init() { detectors.Register(catalog.Backend, ShortCircuitStatementDetector{}) }

// Sin is the sin the detector finds.
func (ShortCircuitStatementDetector) Sin() sins.Sin { return backendsins.ShortCircuitStatement{} }

// Find is every boolean and or or whose value is thrown away.
func (ShortCircuitStatementDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(php.Node.IsShortCircuit)).
		Where(engine.As(php.Node.ResultIsDiscarded)).
		Get()
}
