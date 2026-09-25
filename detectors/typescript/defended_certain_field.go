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
	detectors.Register(catalog.TypeScript, DefendedCertainFieldDetector{})
}

// DefendedCertainFieldDetector finds a ?. on one of the enclosing class's own fields that the class declares
// total: a guard against a case its own type rules out. Only the class's own fields are judged, the class
// being the authority on its own state.
type DefendedCertainFieldDetector struct{}

func (DefendedCertainFieldDetector) Sin() sins.Sin {
	return sin.DefendedCertainField{}
}

func (DefendedCertainFieldDetector) Find(codebase *engine.Codebase) []engine.Match {
	return typescript.In(codebase).
		WhereMemberAccess().
		Where(engine.Answers(engine.NullSafe)).
		Where(engine.As(defendsCertainField)).
		Get()
}

// defendsCertainField says whether the read defends an own field the class declares total.
func defendsCertainField(read typescript.Node) bool {
	field := read.OwnField(read.OwnFieldRead())

	return field.Exists() && !field.IsOptional()
}
