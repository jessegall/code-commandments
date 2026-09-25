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

// DataMethodHintCollisionDetector finds an @method hint on a Data class naming a method it declares for real.
type DataMethodHintCollisionDetector struct{}

func init() { detectors.Register(catalog.Backend, DataMethodHintCollisionDetector{}) }

// Sin is the sin the detector finds.
func (DataMethodHintCollisionDetector) Sin() sins.Sin { return backendsins.DataMethodHintCollision{} }

// Find is every Data class whose @method hint redeclares a real method.
func (DataMethodHintCollisionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(spatienode.Node.IsDataClass)).
		Where(engine.As(php.Node.DocblockMethodTagRedeclaresRealMethod)).
		Get()
}
