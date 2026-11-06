package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// HandRolledWitherDetector finds a wither that rebuilds its class argument by argument where clone-with belongs.
type HandRolledWitherDetector struct{}

func init() { detectors.Register(catalog.PHP, HandRolledWitherDetector{}) }

func (HandRolledWitherDetector) Sin() sins.Sin { return backendsins.HandRolledWither{} }

func (HandRolledWitherDetector) Repentable() {}

func (HandRolledWitherDetector) Find(codebase *engine.Codebase) []engine.Match {
	if !php.SupportsCloneWith(codebase) {
		return nil
	}

	return php.In(codebase).
		WhereNew().
		Where(engine.As(php.Node.IsWitherRebuild)).
		Get()
}

func (HandRolledWitherDetector) CrossFile() {}
