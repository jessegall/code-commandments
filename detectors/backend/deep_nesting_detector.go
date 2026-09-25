package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// DeepNestingDetector finds an if nested two ifs deep within its function.
type DeepNestingDetector struct{}

func init() { detectors.Register(catalog.Backend, DeepNestingDetector{}) }

// Sin is the sin the detector finds.
func (DeepNestingDetector) Sin() sins.Sin { return backendsins.DeepNesting{} }

// Find is every if inside two or more ifs of its own function.
func (DeepNestingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(php.Node.IsDeeplyNestedIf)).
		Get()
}
