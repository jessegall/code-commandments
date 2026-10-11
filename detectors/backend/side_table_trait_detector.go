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
	detectors.Register(catalog.Backend, SideTableTraitDetector{})
}

// Sin is the sin the detector finds.
func (SideTableTraitDetector) Sin() sins.Sin {
	return backendsins.SideTableTrait{}
}

// Find is every enum holding a constant that lists some — not all — of its own cases, which the enum then reads
// to answer for them. Listing every case is an ordering or a vocabulary and says nothing about any one case;
// listing some of them is a trait of those cases, kept where no case can declare it.
func (SideTableTraitDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Enum").
		Where(engine.As(php.Node.ListsSomeOfItsCasesInAConstant)).
		Get()
}
