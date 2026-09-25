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
	detectors.Register(catalog.TypeScript, FalselyOptionalFieldDetector{})
}

// FalselyOptionalFieldDetector finds a field declared optional that is initialised where it is declared: the
// value exists from construction, so the ? claims an absence the object never has.
type FalselyOptionalFieldDetector struct{}

func (FalselyOptionalFieldDetector) Sin() sins.Sin {
	return sin.FalselyOptionalField{}
}

func (FalselyOptionalFieldDetector) Find(codebase *engine.Codebase) []engine.Match {
	return typescript.In(codebase).
		WhereField().
		Where(engine.As(typescript.Node.IsOptional)).
		Where(engine.As(func(field typescript.Node) bool { return field.Initializer().Exists() })).
		Reject(engine.As(func(field typescript.Node) bool { return field.Initializer().IsAbsence() })).
		Get()
}
