package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	spatienode "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/spatie"
)

// ConstructorOrchestrationDetector finds a page object computing its public slots in its constructor, where computed slots belong.
type ConstructorOrchestrationDetector struct{}

func init() { detectors.Register(catalog.Backend, ConstructorOrchestrationDetector{}) }

// Sin is the sin the detector finds.
func (ConstructorOrchestrationDetector) Sin() sins.Sin { return backendsins.ConstructorOrchestration{} }

// Find is every unconditional, once-made assignment of a public slot in a page object constructor from its own state.
func (ConstructorOrchestrationDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Expr_Assign").
		Where(engine.As(php.Node.IsThisPropertyAssignment)).
		Where(func(n engine.Match) bool { return php.EnclosingFunctionName(n) == "__construct" }).
		Where(engine.As(spatienode.Node.IsPageObject)).
		Where(engine.As(spatienode.Node.AssignedPropertyIsPublicSlot)).
		Reject(engine.As(spatienode.Node.AssignmentRhsIsDeferred)).
		Reject(engine.As(spatienode.Node.AssignedSlotTypeIsDeferred)).
		Reject(engine.As(php.Node.AssignmentReferencesLocalVariable)).
		Reject(engine.As(php.Node.IsWithinBranch)).
		Reject(engine.As(spatienode.Node.PropertyAssignedMoreThanOnce)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (ConstructorOrchestrationDetector) Repentable() {}
