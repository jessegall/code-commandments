package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// UnusedParameterDetector finds the unused-parameter sin.
type UnusedParameterDetector struct{}

func init() { detectors.Register(catalog.PHP, UnusedParameterDetector{}) }

func (UnusedParameterDetector) Sin() sins.Sin { return backendsins.UnusedParameter{} }

func (UnusedParameterDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsUnusedParameter)).
		Reject(engine.Match.IsTest).
		Get()
}
