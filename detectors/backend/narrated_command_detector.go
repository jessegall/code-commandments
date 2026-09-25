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

// NarratedCommandDetector finds a command method named as a third-person verb instead of an imperative.
type NarratedCommandDetector struct{}

func init() { detectors.Register(catalog.Backend, NarratedCommandDetector{}) }

// Sin is the sin the detector finds.
func (NarratedCommandDetector) Sin() sins.Sin { return backendsins.NarratedCommand{} }

// Find is every command method, not magic nor inherited, whose name narrates a verb, unless it declares a relation
// to its argument.
func (NarratedCommandDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereKind("Stmt_ClassMethod").
		Reject(engine.As(php.Node.IsMagicMethod)).
		Reject(engine.As(php.Node.NameIsInherited)).
		Where(engine.As(php.Node.IsCommandMethod)).
		Where(engine.As(func(n php.Node) bool { return prose.IsThirdPerson(n.MethodName()) })).
		Reject(engine.As(declaresAConstraint)).
		Get()
}

// declaresAConstraint says whether a command returning its instance names a relation to its argument, as a
// fluent constraint does: matchesWith($other).
func declaresAConstraint(node php.Node) bool {
	returnsNothing := node.DeclaredReturnType() == "void" || node.DeclaredReturnType() == "never"
	if node.IsCommandMethod() && returnsNothing || node.TakesNoArguments() {
		return false
	}

	return prose.IsRelationalCompound(node.MethodName())
}
