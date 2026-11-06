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

func init() { detectors.Register(catalog.PHP, ArrayBagDetector{}) }

func (ArrayBagDetector) Sin() sins.Sin { return backendsins.ArrayBag{} }

func (ArrayBagDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.NoContainer, By: []packages.By{packages.EnclosingClass}}}
}

func (d ArrayBagDetector) Find(codebase *engine.Codebase) []engine.Match {
	return packages.Exempt(codebase, d, codebase.
		Where(engine.As(php.Node.ArrayKeyIsString)).
		Where(engine.As(func(n php.Node) bool { return n.EnclosingParamIsArray(n.ArrayBaseName()) })).
		Reject(engine.As(php.Node.IsWithinSerializationBoundary)).
		Reject(engine.As(php.Node.IsWithinNamedConstructor)).
		Reject(engine.As(php.Node.IsWithinInheritedMethod)).
		Reject(engine.As(php.Node.IsWithinADataFile)).
		Reject(engine.Match.IsTest).
		Get())
}

func (ArrayBagDetector) CrossFile() {}
