package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ConstructorSideEffectDetector finds a constructor that sends work to a collaborator and discards the answer: construction with a side effect.
type ConstructorSideEffectDetector struct{}

func init() { detectors.Register(catalog.Backend, ConstructorSideEffectDetector{}) }

// Sin is the sin the detector finds.
func (ConstructorSideEffectDetector) Sin() sins.Sin { return backendsins.ConstructorSideEffect{} }

// Find is every class whose constructor sends a method to a parameter or an own property as a statement.
func (ConstructorSideEffectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(php.Node.ConstructorHasSideEffect)).
		Get()
}
