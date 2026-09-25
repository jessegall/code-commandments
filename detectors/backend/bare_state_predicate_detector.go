package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/prose"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// BareStatePredicateDetector finds a bool method about its own state named as a third-person verb instead of a question.
type BareStatePredicateDetector struct{}

func init() { detectors.Register(catalog.Backend, BareStatePredicateDetector{}) }

// Sin is the sin the detector finds.
func (BareStatePredicateDetector) Sin() sins.Sin { return backendsins.BareStatePredicate{} }

// Find is every parameterless bool method, not magic nor inherited, whose name narrates a verb rather than asks.
func (BareStatePredicateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Reject(engine.As(php.Node.IsMagicMethod)).
		Reject(engine.As(php.Node.NameIsInherited)).
		Where(engine.As(php.Node.ReturnsBool)).
		Where(engine.As(php.Node.TakesNoArguments)).
		Reject(engine.As(func(n php.Node) bool { return prose.ReadsAsQuestion(n.MethodName()) })).
		Where(engine.As(func(n php.Node) bool { return prose.IsThirdPerson(n.MethodName()) })).
		Get()
}
