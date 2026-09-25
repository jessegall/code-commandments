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

// ManualInputCastDetector finds a field that every construction builds by hand from raw input, where a cast belongs.
type ManualInputCastDetector struct{}

func init() { detectors.Register(catalog.Backend, ManualInputCastDetector{}) }

// Sin is the sin the detector finds.
func (ManualInputCastDetector) Sin() sins.Sin { return backendsins.ManualInputCast{} }

// Find is every field every construction hand-builds.
func (ManualInputCastDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsField)).
		Where(engine.As(spatienode.Node.AlwaysHandBuiltAtConstruction)).
		Get()
}
