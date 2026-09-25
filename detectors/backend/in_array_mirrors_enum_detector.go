package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// InArrayMirrorsEnumDetector finds an in_array over string literals that spell an enum's values.
type InArrayMirrorsEnumDetector struct{}

func init() { detectors.Register(catalog.Backend, InArrayMirrorsEnumDetector{}) }

// Sin is the sin the detector finds.
func (InArrayMirrorsEnumDetector) Sin() sins.Sin { return backendsins.InArrayMirrorsEnum{} }

// Find is every in_array whose array literal holds two or more of one enum's values and nothing else.
func (InArrayMirrorsEnumDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.CallsFunction("in_array") })).
		Where(engine.As(func(n php.Node) bool { return php.EnumsOf(codebase).MirroredBy(n.ArgumentArrayLiterals(1)) })).
		Get()
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (InArrayMirrorsEnumDetector) WholeTree() {}
