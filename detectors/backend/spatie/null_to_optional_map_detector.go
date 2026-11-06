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

// NullToOptionalMapDetector finds a null mapped to Optional by hand, where the Data class should declare it.
type NullToOptionalMapDetector struct{}

func init() { detectors.Register(catalog.PHP, NullToOptionalMapDetector{}) }

func (NullToOptionalMapDetector) Sin() sins.Sin { return backendsins.NullToOptionalMap{} }

func (NullToOptionalMapDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(spatienode.Node.IsOptionalNullFallback)).
		Reject(engine.As(spatienode.Node.IsSharedOptionalFactory)).
		Get()
}
