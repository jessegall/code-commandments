package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ImpossibleGuardDetector finds a comparison with null on a value whose own declared type has ruled it out.
type ImpossibleGuardDetector struct{}

func init() { detectors.Register(catalog.PHP, ImpossibleGuardDetector{}) }

// Sin is the sin the detector finds.
func (ImpossibleGuardDetector) Sin() sins.Sin { return backendsins.ImpossibleGuard{} }

// Find is every guard a declared type has already ruled out.
func (ImpossibleGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := php.ProgramOf(codebase)

	return php.In(codebase).
		WhereKind("Expr_BinaryOp_Identical", "Expr_BinaryOp_NotIdentical").
		Where(engine.As(program.GuardsWhatTheTypeRulesOut)).
		Get()
}
