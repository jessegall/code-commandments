package backend

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// ManufacturedFakeFillDetector finds an argument filled with an empty fallback, manufacturing a value the callee then trusts.
type ManufacturedFakeFillDetector struct{}

func init() { detectors.Register(catalog.Backend, ManufacturedFakeFillDetector{}) }

// Sin is the sin the detector finds.
func (ManufacturedFakeFillDetector) Sin() sins.Sin { return backendsins.ManufacturedFakeFill{} }

// Find is every ?? with an empty non-array fallback that fills an argument, unless the fallback is the default the
// parameter declares.
func (ManufacturedFakeFillDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsCoalesce)).
		Where(engine.As(func(n php.Node) bool { return n.CoalesceRight().IsEmptyLiteral() })).
		Reject(engine.As(func(n php.Node) bool { return n.CoalesceRight().IsEmptyArrayLiteral() })).
		Where(engine.As(php.Node.FillsArgument)).
		Reject(func(m engine.Match) bool { return fallsBackToTheDefault(codebase, m) }).
		Get()
}

// fallsBackToTheDefault says whether the fallback is the default the filled parameter declares: a missing value then
// becomes what leaving the argument out would, and nothing is manufactured.
func fallsBackToTheDefault(codebase *engine.Codebase, coalesce engine.Match) bool {
	argument := coalesce.Parent()
	for strings.HasPrefix(argument.Kind(), "Expr_Cast_") {
		argument = argument.Parent()
	}
	declared := php.ParameterFilled(codebase, argument).Child("default")

	return declared.SameSyntax(coalesce.Child("right"))
}
