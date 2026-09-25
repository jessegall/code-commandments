package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// IfElseLadderDetector finds an if that climbs through two or more elseifs.
type IfElseLadderDetector struct{}

func init() { detectors.Register(catalog.Backend, IfElseLadderDetector{}) }

// Sin is the sin the detector finds.
func (IfElseLadderDetector) Sin() sins.Sin { return backendsins.IfElseLadder{} }

// Find is every if with two or more elseifs.
func (IfElseLadderDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsIfElseLadder)).
		Get()
}
