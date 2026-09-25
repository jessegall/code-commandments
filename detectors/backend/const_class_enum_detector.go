package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ConstClassEnumDetector finds a class of nothing but scalar constants: a closed set of values that wants to be an enum.
type ConstClassEnumDetector struct{}

func init() { detectors.Register(catalog.Backend, ConstClassEnumDetector{}) }

// Sin is the sin the detector finds.
func (ConstClassEnumDetector) Sin() sins.Sin { return backendsins.ConstClassEnum{} }

// Find is every parentless class holding only two or more scalar constants.
func (ConstClassEnumDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereKind("Stmt_Class").
		Where(engine.As(php.Node.IsScalarConstClass)).
		Reject(engine.As(php.Node.ExtendsAClass)).
		Get()
}
