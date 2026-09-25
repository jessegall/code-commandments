package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// NullableCallbackDetector finds a nullable callback parameter defaulted to null that the method checks for null before calling.
type NullableCallbackDetector struct{}

func init() { detectors.Register(catalog.Backend, NullableCallbackDetector{}) }

// Sin is the sin the detector finds.
func (NullableCallbackDetector) Sin() sins.Sin { return backendsins.NullableCallback{} }

// Find is every method with a null-defaulted nullable callable it both null-checks and calls.
func (NullableCallbackDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Where(engine.As(php.Node.HasNullNormalisedNullableCallback)).
		Get()
}
