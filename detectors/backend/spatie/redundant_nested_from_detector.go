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

// RedundantNestedFromDetector finds a nested Data built with ::from() by hand where the parent would build it itself.
type RedundantNestedFromDetector struct{}

func init() { detectors.Register(catalog.Backend, RedundantNestedFromDetector{}) }

// Sin is the sin the detector finds.
func (RedundantNestedFromDetector) Sin() sins.Sin { return backendsins.RedundantNestedFrom{} }

// Find is every ::from() of an array literal on a Data class hydrating a slot the parent builds, save per-item and cast slots.
func (RedundantNestedFromDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Expr_StaticCall").
		Where(func(n engine.Match) bool { return n.Child("name").Name() == "from" }).
		Where(engine.As(spatienode.Node.OnDataClass)).
		Where(engine.As(spatienode.Node.FromArgIsArrayLiteral)).
		Reject(engine.As(spatienode.Node.IsPerItemHydration)).
		Where(engine.As(spatienode.Node.HydratesAnAutoBuiltSlot)).
		Reject(engine.As(spatienode.Node.HydrationSlotHasCast)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (RedundantNestedFromDetector) Repentable() {}
