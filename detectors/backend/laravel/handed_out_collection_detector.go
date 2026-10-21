package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/sins"
	laravelsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

// HandedOutCollectionDetector finds a getter whose whole body hands back the Collection the class holds.
type HandedOutCollectionDetector struct{}

func init() { detectors.Register(catalog.PHP, HandedOutCollectionDetector{}) }

// Sin is the sin the detector finds.
func (HandedOutCollectionDetector) Sin() sins.Sin { return laravelsins.HandedOutCollection{} }

// Find is every method that hands its caller the class's own Collection.
func (HandedOutCollectionDetector) Find(codebase *engine.Codebase) []engine.Match {
	types := php.TypesOf(codebase)

	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Where(engine.As(func(n laravel.Node) bool { return n.HandsOutItsOwnCollection(types.TypeOf) })).
		Get()
}
