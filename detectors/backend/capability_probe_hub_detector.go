package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// CapabilityProbeHubDetector finds a class growing one instanceof-guarded accessor per optional capability of a held object.
type CapabilityProbeHubDetector struct{}

func init() { detectors.Register(catalog.PHP, CapabilityProbeHubDetector{}) }

func (CapabilityProbeHubDetector) Sin() sins.Sin { return backendsins.CapabilityProbeHub{} }

func (CapabilityProbeHubDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(php.Node.ProbesOneFieldForCapabilities)).
		Reject(engine.Match.IsTest).
		Get()
}
