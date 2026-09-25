package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// RedundantElseDetector finds an else after an if whose body already leaves.
type RedundantElseDetector struct{}

func init() { detectors.Register(catalog.Backend, RedundantElseDetector{}) }

// Sin is the sin the detector finds.
func (RedundantElseDetector) Sin() sins.Sin { return backendsins.RedundantElse{} }

// Find is every if with an else whose own body ends by leaving.
func (RedundantElseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(php.Node.HasRedundantElse)).
		Get()
}
