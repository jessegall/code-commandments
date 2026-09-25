package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// duplicateWeight is how many nodes a body must weigh before two alike are not a coincidence.
const duplicateWeight = 12

// DuplicateFunctionDetector finds two or more defs with the same body.
type DuplicateFunctionDetector struct{}

func init() {
	detectors.Register(catalog.Python, DuplicateFunctionDetector{})
}

// Sin is the sin the detector finds.
func (DuplicateFunctionDetector) Sin() sins.Sin {
	return pysins.DuplicateFunction{}
}

// Find is every place the sin is committed.
func (DuplicateFunctionDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := py.In(codebase).
		WhereFunction().
		Where(engine.As(func(n py.Node) bool { return n.BodyWeight() >= duplicateWeight })).
		Reject(engine.As(py.Node.IsConstructorDeclaration)).
		Get()

	return flatten(engine.RecurringBuckets(candidates, bodyHash, 2))
}

// bodyHash is the fingerprint a def's body is grouped by.
func bodyHash(match engine.Match) (string, bool) {
	return py.Node{Match: match}.BodyHash(), true
}
