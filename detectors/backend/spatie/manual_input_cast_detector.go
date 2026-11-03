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

func init() { detectors.Register(catalog.PHP, ManualInputCastDetector{}) }

// Sin is the sin the detector finds.
func (ManualInputCastDetector) Sin() sins.Sin { return backendsins.ManualInputCast{} }

// Find is every field every construction hand-builds.
func (ManualInputCastDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsField)).
		Where(engine.As(spatienode.Node.AlwaysHandBuiltAtConstruction)).
		Get()
}

// CrossFile says the rule reads more than the file it reports in, which the fixture measures by asking it
// of one file and of the whole tree: a scoped run reads the whole scan while it is enabled.
func (ManualInputCastDetector) CrossFile() {}
