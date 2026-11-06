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

func init() { detectors.Register(catalog.PHP, InArrayMirrorsEnumDetector{}) }

func (InArrayMirrorsEnumDetector) Sin() sins.Sin { return backendsins.InArrayMirrorsEnum{} }

func (InArrayMirrorsEnumDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.CallsFunction("in_array") })).
		Where(engine.As(func(n php.Node) bool { return php.EnumsOf(codebase).MirroredBy(n.ArgumentArrayLiterals(1)) })).
		Get()
}

func (InArrayMirrorsEnumDetector) CrossFile() {}
