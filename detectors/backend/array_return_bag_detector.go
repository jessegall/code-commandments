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

func init() { detectors.Register(catalog.PHP, ArrayReturnBagDetector{}) }

func (ArrayReturnBagDetector) Sin() sins.Sin { return backendsins.ArrayReturnBag{} }

func (ArrayReturnBagDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{
		{Tag: packages.ArrayReturning, By: []packages.By{packages.EnclosingClass}},
		{Tag: packages.ContractMethod, By: []packages.By{packages.EnclosingMethod}},
	}
}

func (d ArrayReturnBagDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := php.ProgramOf(codebase)

	return packages.Exempt(codebase, d, codebase.
		Where(engine.As(func(n php.Node) bool { return len(n.StringKeys()) >= 2 })).
		Where(engine.As(php.Node.IsReturnedValue)).
		Reject(engine.As(func(n php.Node) bool { return !n.EnclosingFunctionLike().Exists() })).
		Reject(engine.As(php.Node.HasNestedArrayValue)).
		Reject(engine.As(php.Node.SpreadsAnotherArray)).
		Reject(engine.As(php.Node.IsWithinADataFile)).
		Reject(engine.As(php.Node.LooksLikeJsonSchema)).
		Reject(engine.As(php.Node.IsHomogeneousLookupTable)).
		Reject(engine.As(php.Node.ProjectsTypedObject)).
		Reject(engine.As(php.Node.EnclosingFunctionReturnsShapedArray)).
		Reject(func(n engine.Match) bool {
			return program.OverridesMethod(php.EnclosingClassName(n), php.EnclosingFunctionName(n))
		}).
		Get())
}

func (ArrayReturnBagDetector) CrossFile() {}
