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

// ManualOutputTransformDetector finds a Data class flattening a value object into an array by hand, where a
// transformer belongs.
type ManualOutputTransformDetector struct{}

func init() { detectors.Register(catalog.Backend, ManualOutputTransformDetector{}) }

// Sin is the sin the detector finds.
func (ManualOutputTransformDetector) Sin() sins.Sin { return backendsins.ManualOutputTransform{} }

// Find is every get hook, #[Computed] method and assignment of a Data class that flattens a value object to an array.
func (ManualOutputTransformDetector) Find(codebase *engine.Codebase) []engine.Match {
	flattens := engine.As(func(n spatienode.Node) bool { return n.IsDataClass() && n.FlattensValueObjectToArray() })
	hooks := php.In(codebase).WhereKind("PropertyHook").Where(func(n engine.Match) bool { return n.Name() == "get" }).Where(flattens).Get()
	computed := php.In(codebase).WhereKind("Stmt_ClassMethod").Where(engine.As(func(n php.Node) bool { return n.HasAttribute("Computed") })).Where(flattens).Get()
	assigned := php.In(codebase).WhereKind("Expr_Assign").Where(flattens).Get()

	return append(append(hooks, computed...), assigned...)
}
