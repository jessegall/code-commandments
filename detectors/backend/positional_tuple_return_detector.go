package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// PositionalTupleReturnDetector finds a function returning an unkeyed array of values from different sources, a tuple whose meaning lives in positions.
type PositionalTupleReturnDetector struct{}

func init() { detectors.Register(catalog.Backend, PositionalTupleReturnDetector{}) }

// Sin is the sin the detector finds.
func (PositionalTupleReturnDetector) Sin() sins.Sin { return backendsins.PositionalTupleReturn{} }

// Find is every returned unkeyed array of three or more items reading two or more variables, unless the function documents a list.
func (PositionalTupleReturnDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.As(php.Node.IsPositionalTuple)).
		Where(engine.As(php.Node.IsReturnExpression)).
		Reject(engine.As(php.Node.EnclosingFunctionReturnsSequence)).
		Get()
}
