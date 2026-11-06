package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// TrainWreckDetector finds a chain of sends crossing three or more of the project's own types.
type TrainWreckDetector struct{}

func init() { detectors.Register(catalog.PHP, TrainWreckDetector{}) }

func (TrainWreckDetector) Sin() sins.Sin { return backendsins.TrainWreck{} }

func (TrainWreckDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.ReachesThroughThreeTypes)).
		Get()
}

func (TrainWreckDetector) CrossFile() {}
