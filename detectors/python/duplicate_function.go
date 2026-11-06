package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

const duplicateWeight = 12

// DuplicateFunctionDetector finds two or more defs with the same body.
type DuplicateFunctionDetector struct{}

func init() {
	detectors.Register(catalog.Python, DuplicateFunctionDetector{})
}

func (DuplicateFunctionDetector) Sin() sins.Sin {
	return pysins.DuplicateFunction{}
}

func (DuplicateFunctionDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := py.In(codebase).
		WhereFunction().
		Where(engine.As(func(n py.Node) bool { return n.BodyWeight() >= duplicateWeight })).
		Reject(engine.As(py.Node.IsConstructorDeclaration)).
		Get()

	return flatten(engine.RecurringBuckets(candidates, bodyHash, 2))
}

func bodyHash(match engine.Match) (string, bool) {
	return py.Node{Match: match}.BodyHash(), true
}

func (DuplicateFunctionDetector) GroupKey(match engine.Match) (string, bool) {
	hash, _ := bodyHash(match)

	return hash, hash != ""
}

func (DuplicateFunctionDetector) CrossFile() {}
