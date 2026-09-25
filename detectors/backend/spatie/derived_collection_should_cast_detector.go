package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	spatienode "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/spatie"
)

// DerivedCollectionShouldCastDetector finds an array_map building the elements of a Data collection by hand, where a cast belongs.
type DerivedCollectionShouldCastDetector struct{}

func init() { detectors.Register(catalog.Backend, DerivedCollectionShouldCastDetector{}) }

// Sin is the sin the detector finds.
func (DerivedCollectionShouldCastDetector) Sin() sins.Sin {
	return backendsins.DerivedCollectionCast{}
}

// Find is every array_map whose mapped factory derives the element.
func (DerivedCollectionShouldCastDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.CallsFunction("array_map") })).
		Where(engine.As(spatienode.Node.MappedFactoryDerivesElement)).
		Get()
}
