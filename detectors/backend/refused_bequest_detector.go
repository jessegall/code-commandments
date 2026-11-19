package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// RefusedBequestDetector finds the refused-bequest sin.
type RefusedBequestDetector struct{}

func init() { detectors.Register(catalog.PHP, RefusedBequestDetector{}) }

func (RefusedBequestDetector) Sin() sins.Sin { return backendsins.RefusedBequest{} }

func (RefusedBequestDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Where(engine.As(php.Node.RefusesWhatItInherits)).
		Reject(engine.Match.IsTest).
		Get()
}
