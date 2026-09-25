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

// HandKeyRemapDetector finds keys renamed by hand before ::from(), where a MapInputName belongs.
type HandKeyRemapDetector struct{}

func init() { detectors.Register(catalog.Backend, HandKeyRemapDetector{}) }

// Sin is the sin the detector finds.
func (HandKeyRemapDetector) Sin() sins.Sin { return backendsins.HandKeyRemap{} }

// Find is every ::from() fed a hand-remapped array.
func (HandKeyRemapDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Expr_StaticCall").
		Where(func(n engine.Match) bool { return n.Child("name").Name() == "from" }).
		Where(engine.As(spatienode.Node.IsHandKeyRemap)).
		Get()
}
