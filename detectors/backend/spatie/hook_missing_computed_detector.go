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

// HookMissingComputedDetector finds a get hook on a Data class that is not marked #[Computed].
type HookMissingComputedDetector struct{}

func init() { detectors.Register(catalog.Backend, HookMissingComputedDetector{}) }

// Sin is the sin the detector finds.
func (HookMissingComputedDetector) Sin() sins.Sin { return backendsins.HookMissingComputed{} }

// Find is every get hook of a Data class missing #[Computed].
func (HookMissingComputedDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("PropertyHook").
		Where(func(n engine.Match) bool { return n.Name() == "get" }).
		Where(engine.As(spatienode.Node.HookMissingComputed)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (HookMissingComputedDetector) Repentable() {}
