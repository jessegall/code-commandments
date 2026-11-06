package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// SideTableTraitDetector finds an enum keeping a trait of some of its cases in a constant list of them.
type SideTableTraitDetector struct{}

func init() {
	detectors.Register(catalog.PHP, SideTableTraitDetector{})
}

func (SideTableTraitDetector) Sin() sins.Sin {
	return backendsins.SideTableTrait{}
}

func (SideTableTraitDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Enum").
		Where(engine.As(php.Node.ListsSomeOfItsCasesInAConstant)).
		Get()
}
