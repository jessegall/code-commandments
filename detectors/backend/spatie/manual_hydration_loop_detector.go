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

// ManualHydrationLoopDetector finds a Data class hydrated item by item in a loop, where collect() belongs.
type ManualHydrationLoopDetector struct{}

func init() { detectors.Register(catalog.PHP, ManualHydrationLoopDetector{}) }

func (ManualHydrationLoopDetector) Sin() sins.Sin { return backendsins.ManualHydrationLoop{} }

func (ManualHydrationLoopDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Expr_StaticCall").
		Where(func(n engine.Match) bool { return n.Child("name").Name() == "from" }).
		Where(engine.As(spatienode.Node.OnDataClass)).
		Where(engine.As(spatienode.Node.IsPerItemHydration)).
		Reject(engine.As(spatienode.Node.IsConditionalConstruction)).
		Reject(engine.As(spatienode.Node.IsWithinTolerantCatch)).
		Reject(engine.As(spatienode.Node.IsKeyedMapAssignment)).
		Reject(engine.As(spatienode.Node.IsInlineProjection)).
		Get()
}

func (ManualHydrationLoopDetector) Repentable() {}

func (ManualHydrationLoopDetector) CrossFile() {}
