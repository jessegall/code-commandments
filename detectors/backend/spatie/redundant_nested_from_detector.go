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

func init() { detectors.Register(catalog.PHP, RedundantNestedFromDetector{}) }

func (RedundantNestedFromDetector) Sin() sins.Sin { return backendsins.RedundantNestedFrom{} }

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

func (RedundantNestedFromDetector) Repentable() {}

func (RedundantNestedFromDetector) CrossFile() {}
