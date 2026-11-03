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

// Sin is the sin the detector finds.
func (TrainWreckDetector) Sin() sins.Sin { return backendsins.TrainWreck{} }

// Find is every chain that walks through three of the project's types to reach one answer.
func (TrainWreckDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.ReachesThroughThreeTypes)).
		Get()
}

// CrossFile says the rule reads more than the file it reports in, which the fixture measures by asking it
// of one file and of the whole tree: a scoped run reads the whole scan while it is enabled.
func (TrainWreckDetector) CrossFile() {}
