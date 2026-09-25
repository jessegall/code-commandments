package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// nearDuplicateWeight is how many nodes a body must weigh before two of one shape are not a coincidence.
const nearDuplicateWeight = 20

// NearDuplicateFunctionDetector finds two or more defs with one body's shape that differ only in their names and literals.
type NearDuplicateFunctionDetector struct{}

func init() {
	detectors.Register(catalog.Python, NearDuplicateFunctionDetector{})
}

// Sin is the sin the detector finds.
func (NearDuplicateFunctionDetector) Sin() sins.Sin {
	return pysins.NearDuplicateFunction{}
}

// Find is every place the sin is committed.
func (NearDuplicateFunctionDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := py.In(codebase).
		WhereFunction().
		Where(engine.As(func(n py.Node) bool { return n.BodyWeight() >= nearDuplicateWeight })).
		Reject(engine.As(py.Node.IsConstructorDeclaration)).
		Reject(engine.As(py.Node.IsSoleReturnExpression)).
		Reject(engine.As(py.Node.IsSoleExpressionStatement)).
		Reject(engine.As(py.Node.IsLiteralLookup)).
		Reject(engine.As(py.Node.IsStub)).
		Get()

	return engine.NearCopies(candidates, shapeHash, bodyHash)
}

// shapeHash is the fingerprint of a def's body, blind to its names and literals.
func shapeHash(match engine.Match) (string, bool) {
	return py.Node{Match: match}.ShapeHash(), true
}

// GroupKey groups a finding with the defs whose body has its shape.
func (NearDuplicateFunctionDetector) GroupKey(match engine.Match) (string, bool) {
	shape, _ := shapeHash(match)

	return shape, shape != ""
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (NearDuplicateFunctionDetector) WholeTree() {}
