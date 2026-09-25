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

// ArrayReturnBagDetector finds a function returning an array of named values that wants a type.
type ArrayReturnBagDetector struct{}

func init() { detectors.Register(catalog.Backend, ArrayReturnBagDetector{}) }

// Sin is the sin the detector finds.
func (ArrayReturnBagDetector) Sin() sins.Sin { return backendsins.ArrayReturnBag{} }

// Exemptions excuses a class whose job is handing the framework arrays, and a method whose signature it dictates.
func (ArrayReturnBagDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{
		{Tag: packages.ArrayReturning, By: []packages.By{packages.EnclosingClass}},
		{Tag: packages.ContractMethod, By: []packages.By{packages.EnclosingMethod}},
	}
}

// Find is every returned array literal with two or more string keys, unless it nests, spreads, is a JSON schema or a
// lookup table, projects a type that already exists, is documented as a shape, or answers to an ancestor's method.
func (d ArrayReturnBagDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := php.ProgramOf(codebase)

	return packages.Exempt(codebase, d, codebase.
		Where(engine.As(func(n php.Node) bool { return len(n.StringKeys()) >= 2 })).
		Where(engine.As(php.Node.IsReturnedValue)).
		Reject(engine.As(func(n php.Node) bool { return !n.EnclosingFunctionLike().Exists() })).
		Reject(engine.As(php.Node.HasNestedArrayValue)).
		Reject(engine.As(php.Node.SpreadsAnotherArray)).
		Reject(engine.As(php.Node.LooksLikeJsonSchema)).
		Reject(engine.As(php.Node.IsHomogeneousLookupTable)).
		Reject(engine.As(php.Node.ProjectsTypedObject)).
		Reject(engine.As(php.Node.EnclosingFunctionReturnsShapedArray)).
		Reject(func(n engine.Match) bool {
			return program.OverridesMethod(php.EnclosingClassName(n), php.EnclosingFunctionName(n))
		}).
		Get())
}
