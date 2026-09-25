package typescript

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/sins"
	sin "github.com/jessegall/code-commandments/sins/typescript"
)

func init() {
	detectors.Register(catalog.TypeScript, NearDuplicateFunctionDetector{})
}

// nearDuplicateWeight is how much code a body must hold before a near copy of it is worth one shared function.
const nearDuplicateWeight = 20

// NearDuplicateFunctionDetector finds function bodies that are the same code with different names and data:
// one function parameterised by what differs. A constructor, a body that is one return or one expression,
// and a table of literal answers are left alone; their likeness is their form, not a copy.
type NearDuplicateFunctionDetector struct{}

func (NearDuplicateFunctionDetector) Sin() sins.Sin {
	return sin.NearDuplicateFunction{}
}

func (NearDuplicateFunctionDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := typescript.In(codebase).
		WhereFunction().
		Where(engine.As(func(function typescript.Node) bool { return function.BodyWeight() >= nearDuplicateWeight })).
		Reject(engine.As(func(function typescript.Node) bool { return function.Kind() == "Constructor" })).
		Reject(engine.As(func(function typescript.Node) bool { return function.SoleStatement().Kind() == "ReturnStatement" })).
		Reject(engine.As(func(function typescript.Node) bool { return function.SoleStatement().Kind() == "ExpressionStatement" })).
		Reject(engine.As(typescript.Node.IsLiteralLookup)).
		Get()

	return detectors.NearCopies(candidates, bodyShape, bodyHash)
}

func bodyShape(function engine.Match) string {
	return typescript.Of(function).BodyShape()
}

// GroupKey groups a finding with the functions whose body has its shape.
func (NearDuplicateFunctionDetector) GroupKey(match engine.Match) (string, bool) {
	shape := bodyShape(match)

	return shape, shape != ""
}
