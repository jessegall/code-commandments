package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// MaskedInvariantDetector finds a ?? papering a literal over a nullsafe read of transient own state, defending a value the design always has set.
type MaskedInvariantDetector struct{}

func init() { detectors.Register(catalog.Backend, MaskedInvariantDetector{}) }

// Sin is the sin the detector finds.
func (MaskedInvariantDetector) Sin() sins.Sin { return backendsins.MaskedInvariant{} }

// Find is every ?? with a non-null literal fallback over a nullsafe read of a private nullable property set outside the constructor.
func (MaskedInvariantDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.MasksOwnState)).
		Get()
}
