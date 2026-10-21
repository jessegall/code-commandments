package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// RethrownUnchangedDetector finds a catch whose whole body throws the exception it caught, unchanged.
type RethrownUnchangedDetector struct{}

func init() { detectors.Register(catalog.Backend, RethrownUnchangedDetector{}) }

// Sin is the sin the detector finds.
func (RethrownUnchangedDetector) Sin() sins.Sin { return backendsins.RethrownUnchanged{} }

// Find is every catch that only throws what it caught.
func (RethrownUnchangedDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Catch").
		Where(engine.As(php.Node.RethrowsUnchanged)).
		Get()
}
