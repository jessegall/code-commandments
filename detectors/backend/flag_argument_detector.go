package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// FlagArgumentDetector finds a method whose whole body branches on a bool or absent parameter: two methods sharing one name.
type FlagArgumentDetector struct{}

func init() { detectors.Register(catalog.PHP, FlagArgumentDetector{}) }

func (FlagArgumentDetector) Sin() sins.Sin { return backendsins.FlagArgument{} }

func (FlagArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Reject(engine.As(php.Node.IsConstructorDeclaration)).
		Where(engine.As(func(n php.Node) bool { return n.SwitchesEntirelyOnABoolParam() || n.SwitchesEntirelyOnAnAbsentParam() })).
		Get()
}
