package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// NonCountingForDetector finds a for loop whose steps advance no counter, a while loop in disguise.
type NonCountingForDetector struct{}

func init() { detectors.Register(catalog.Backend, NonCountingForDetector{}) }

// Sin is the sin the detector finds.
func (NonCountingForDetector) Sin() sins.Sin { return backendsins.NonCountingFor{} }

// Find is every for loop whose steps advance no counter.
func (NonCountingForDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsNonCountingFor)).
		Get()
}
