package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// DuplicateFunctionDetector finds two or more methods with the same body, one mechanism written twice.
type DuplicateFunctionDetector struct{}

func init() { detectors.Register(catalog.Backend, DuplicateFunctionDetector{}) }

// minBodyNodes is how many nodes a body must hold before two alike are not a coincidence.
const minBodyNodes = 12

// Sin is the sin the detector finds.
func (DuplicateFunctionDetector) Sin() sins.Sin { return backendsins.DuplicateFunction{} }

// GroupKey is the fingerprint of the method's body.
func (DuplicateFunctionDetector) GroupKey(finding engine.Match) (string, bool) {
	key := (php.Node{Match: finding}).BodyHash()

	return key, key != ""
}

// Find is every sizeable method, guarded accessors, self-seeding factories, one-return bodies and deprecated ones
// aside, whose body another method repeats.
func (d DuplicateFunctionDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Where(engine.As(func(n php.Node) bool { return n.BodyNodeCount() >= minBodyNodes })).
		Reject(engine.As(php.Node.IsGuardedAccessor)).
		Reject(engine.As(php.Node.IsSelfSeedingFactory)).
		Reject(engine.As(php.Node.IsSoleReturnExpression)).
		Reject(engine.As(php.Node.IsDeprecated)).
		Get()

	return recurring(candidates, d.GroupKey, 2, func([]engine.Match) bool { return true })
}
