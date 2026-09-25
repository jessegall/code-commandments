package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/sins"
	sin "github.com/jessegall/code-commandments/sins/typescript"
)

func init() {
	detectors.Register(catalog.TypeScript, DuplicateFunctionDetector{})
}

// duplicateWeight is how much code a body must hold before a copy of it is worth one shared function.
const duplicateWeight = 12

// DuplicateFunctionDetector finds function bodies written twice, the same code formatting aside, across
// modules and components alike: one shared function or composable waiting to be written.
type DuplicateFunctionDetector struct{}

func (DuplicateFunctionDetector) Sin() sins.Sin {
	return sin.DuplicateFunction{}
}

func (DuplicateFunctionDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := typescript.In(codebase).
		WhereFunction().
		Where(engine.As(func(function typescript.Node) bool { return function.BodyWeight() >= duplicateWeight })).
		Get()

	return slices.Concat(detectors.Recurring(candidates, bodyHash)...)
}

func bodyHash(function engine.Match) string {
	return typescript.Of(function).BodyHash()
}

// GroupKey groups a finding with the functions whose body it repeats.
func (DuplicateFunctionDetector) GroupKey(match engine.Match) (string, bool) {
	hash := bodyHash(match)

	return hash, hash != ""
}
