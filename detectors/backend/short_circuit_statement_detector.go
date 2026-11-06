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

func init() { detectors.Register(catalog.PHP, ShortCircuitStatementDetector{}) }

func (ShortCircuitStatementDetector) Sin() sins.Sin { return backendsins.ShortCircuitStatement{} }

func (ShortCircuitStatementDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsShortCircuit)).
		Where(engine.As(php.Node.ResultIsDiscarded)).
		Get()
}

func (ShortCircuitStatementDetector) Repentable() {}
