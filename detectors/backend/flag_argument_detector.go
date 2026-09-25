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

func init() { detectors.Register(catalog.Backend, FlagArgumentDetector{}) }

// Sin is the sin the detector finds.
func (FlagArgumentDetector) Sin() sins.Sin { return backendsins.FlagArgument{} }

// Find is every method, constructors aside, whose body is a two-way branch on a bool parameter or on whether a nullable one is null.
func (FlagArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereKind("Stmt_ClassMethod").
		Reject(engine.As(php.Node.IsConstructorDeclaration)).
		Where(engine.As(func(n php.Node) bool { return n.SwitchesEntirelyOnABoolParam() || n.SwitchesEntirelyOnAnAbsentParam() })).
		Get()
}
