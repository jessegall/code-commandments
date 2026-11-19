package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// MiddleManDetector finds the middle-man sin.
type MiddleManDetector struct{}

func init() { detectors.Register(catalog.PHP, MiddleManDetector{}) }

func (MiddleManDetector) Sin() sins.Sin { return backendsins.MiddleMan{} }

func (MiddleManDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(php.Node.IsMiddleMan)).
		Get()
}
