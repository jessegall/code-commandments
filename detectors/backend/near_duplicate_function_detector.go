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

// NearDuplicateFunctionDetector finds methods of one shape that differ only in names and data: one mechanism copied
// and edited.
type NearDuplicateFunctionDetector struct{}

func init() { detectors.Register(catalog.Backend, NearDuplicateFunctionDetector{}) }

// minNearBodyNodes is how many nodes a body must hold before two of one shape are not a coincidence.
const minNearBodyNodes = 20

// Sin is the sin the detector finds.
func (NearDuplicateFunctionDetector) Sin() sins.Sin { return backendsins.NearDuplicateFunction{} }

// Exemptions lets a package declare the methods whose signature it dictates, whose likeness is the contract's.
func (NearDuplicateFunctionDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.ContractMethod}}
}

// GroupKey is the shape of the method, names and data blanked.
func (NearDuplicateFunctionDetector) GroupKey(finding engine.Match) (string, bool) {
	key := (php.Node{Match: finding}).ShapeHash()

	return key, key != ""
}

// Find is every sizeable method sharing its shape with another that it does not copy exactly, the literal tables,
// generators, constructors, factories, one-liners, guarded accessors, deprecated methods and contract hooks aside.
func (d NearDuplicateFunctionDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Where(engine.As(func(n php.Node) bool { return n.BodyNodeCount() >= minNearBodyNodes })).
		Reject(engine.As(php.Node.ReturnsArrayLiteralOnly)).
		Reject(engine.As(php.Node.YieldsEntriesOnly)).
		Reject(engine.As(php.Node.IsConstructorDeclaration)).
		Reject(engine.As(php.Node.IsSelfSeedingFactory)).
		Reject(engine.As(php.Node.IsSoleReturnExpression)).
		Reject(engine.As(php.Node.IsSoleExpressionStatement)).
		Reject(engine.As(php.Node.IsGuardedAccessor)).
		Reject(engine.As(php.Node.IsDeprecated)).
		Reject(func(n engine.Match) bool {
			return packages.Excuses(codebase, packages.ContractMethod, php.EnclosingClassName(n), php.EnclosingFunctionName(n))
		}).
		Get()
	shape := d.GroupKey
	exact := func(m engine.Match) (string, bool) { return php.StructuralHash(m), true }

	return engine.NearCopies(candidates, shape, exact)
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (NearDuplicateFunctionDetector) WholeTree() {}
