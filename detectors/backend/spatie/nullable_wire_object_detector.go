package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	spatienode "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/spatie"
)

// NullableWireObjectDetector finds a nullable object field on the wire, where the absence belongs modelled on the server.
type NullableWireObjectDetector struct{}

func init() { detectors.Register(catalog.PHP, NullableWireObjectDetector{}) }

func (NullableWireObjectDetector) Sin() sins.Sin { return backendsins.NullableWireObject{} }

func (NullableWireObjectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsField)).
		Where(engine.As(spatienode.Node.NullableWireObject)).
		Get()
}
