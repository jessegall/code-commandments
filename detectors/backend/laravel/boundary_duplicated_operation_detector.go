package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	laravelnode "github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

// BoundaryDuplicatedOperationDetector finds entry points of different kinds — a controller action, a console command, an MCP tool — that each perform one operation themselves: the operation belongs in one class they share.
type BoundaryDuplicatedOperationDetector struct{}

func init() { detectors.Register(catalog.Backend, BoundaryDuplicatedOperationDetector{}) }

// Sin is the sin the detector finds.
func (BoundaryDuplicatedOperationDetector) Sin() sins.Sin {
	return backendsins.BoundaryDuplicatedOperation{}
}

// Find is every method whose operation another kind of entry point repeats.
func (BoundaryDuplicatedOperationDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Where(func(n engine.Match) bool {
			return len(laravelnode.BoundaryOperationsOf(codebase).TwinsOf(php.EnclosingClassName(n), php.EnclosingFunctionName(n))) > 0
		}).
		Get()
}
