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

func init() { detectors.Register(catalog.Backend, HandRolledWitherDetector{}) }

// Sin is the sin the detector finds.
func (HandRolledWitherDetector) Sin() sins.Sin { return backendsins.HandRolledWither{} }

// Repentable says a scribe rewrites the sin away.
func (HandRolledWitherDetector) Repentable() {}

// Find is every new a hand-rolled wither returns, in a project whose PHP can clone with changed properties.
func (HandRolledWitherDetector) Find(codebase *engine.Codebase) []engine.Match {
	if !php.SupportsCloneWith(codebase) {
		return nil
	}

	return php.In(codebase).
		WhereNew().
		Where(engine.As(php.Node.IsWitherRebuild)).
		Get()
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (HandRolledWitherDetector) CrossFile() {}
