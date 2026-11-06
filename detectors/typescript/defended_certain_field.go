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

// DefendedCertainFieldDetector finds a ?.
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

func defendsCertainField(read typescript.Node) bool {
	field := read.OwnField(read.OwnFieldRead())

	return field.Exists() && !field.IsOptional()
}
