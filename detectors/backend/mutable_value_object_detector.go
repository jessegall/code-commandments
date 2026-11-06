package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// MutableValueObjectDetector finds a value object that changes its own fields after construction.
type MutableValueObjectDetector struct{}

func init() { detectors.Register(catalog.PHP, MutableValueObjectDetector{}) }

func (MutableValueObjectDetector) Sin() sins.Sin { return backendsins.MutableValueObject{} }

func (MutableValueObjectDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := php.ProgramOf(codebase)

	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(func(n engine.Match) bool { return program.ClassIsValueType(php.EnclosingClassName(n)) }).
		Where(engine.As(func(n php.Node) bool {
			return n.MutatesOwnFieldsAfterConstruction(program.TraitMethodsOf(php.EnclosingClassName(n.Match)))
		})).
		Get()
}
