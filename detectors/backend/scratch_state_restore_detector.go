package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ScratchStateRestoreDetector finds a method that saves an own property into a local and restores it later: per-call scratch state kept on the object.
type ScratchStateRestoreDetector struct{}

func init() { detectors.Register(catalog.Backend, ScratchStateRestoreDetector{}) }

// Sin is the sin the detector finds.
func (ScratchStateRestoreDetector) Sin() sins.Sin { return backendsins.ScratchStateRestore{} }

// Find is every method that saves and restores an own property through a local.
func (ScratchStateRestoreDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereKind("Stmt_ClassMethod").
		Where(engine.As(php.Node.HasOwnStateSaveAndRestore)).
		Get()
}
