package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// RedundantArrowReturnTypeDetector finds an arrow function whose written return type only restates the type of its expression.
type RedundantArrowReturnTypeDetector struct{}

func init() { detectors.Register(catalog.Backend, RedundantArrowReturnTypeDetector{}) }

// Sin is the sin the detector finds.
func (RedundantArrowReturnTypeDetector) Sin() sins.Sin { return backendsins.RedundantArrowReturnType{} }

// Find is every arrow function whose return type is its expression's plain type.
func (RedundantArrowReturnTypeDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Expr_ArrowFunction").
		Where(engine.As(php.Node.HasReturnType)).
		Where(engine.As(php.Node.ReturnTypeRestatesItsExpression)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (RedundantArrowReturnTypeDetector) Repentable() {}
