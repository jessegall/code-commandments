package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ArrayBagDetector finds an array parameter read by string keys: a bag of named values that wants a type.
type ArrayBagDetector struct{}

func init() { detectors.Register(catalog.Backend, ArrayBagDetector{}) }

// Sin is the sin the detector finds.
func (ArrayBagDetector) Sin() sins.Sin { return backendsins.ArrayBag{} }

// Exemptions excuses a class the framework builds without the container, whose array parameter is its convention.
func (ArrayBagDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.NoContainer, By: []packages.By{packages.EnclosingClass}}}
}

// Find is every string-keyed read of an array parameter, outside the serialization boundary, named constructors
// and test code, which reads the payload production code emits: the bag is judged where it is born.
func (d ArrayBagDetector) Find(codebase *engine.Codebase) []engine.Match {
	return packages.Exempt(codebase, d, codebase.
		Where(engine.As(php.Node.ArrayKeyIsString)).
		Where(engine.As(func(n php.Node) bool { return n.EnclosingParamIsArray(n.ArrayBaseName()) })).
		Reject(engine.As(php.Node.IsWithinSerializationBoundary)).
		Reject(engine.As(php.Node.IsWithinNamedConstructor)).
		Reject(engine.Match.IsTest).
		Get())
}
