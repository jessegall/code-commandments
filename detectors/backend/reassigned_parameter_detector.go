package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ReassignedParameterDetector finds the reassigned-parameter sin.
type ReassignedParameterDetector struct{}

func init() { detectors.Register(catalog.PHP, ReassignedParameterDetector{}) }

func (ReassignedParameterDetector) Sin() sins.Sin { return backendsins.ReassignedParameter{} }

func (ReassignedParameterDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.ReassignsAParameter)).
		Reject(engine.Match.IsTest).
		Get()
}
