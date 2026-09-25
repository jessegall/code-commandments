package frontend

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/published"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
	"github.com/jessegall/code-commandments/typescript"
)

func init() {
	detectors.Register(catalog.Frontend, MirroredServerTypeDetector{})
}

// mirrorFields is the fewest fields a type may have and still be judged a mirror: below it, a name and a few
// shared fields are too weak to trust.
const mirrorFields = 3

// MirroredServerTypeDetector finds a hand-written TypeScript type that copies a type the server publishes:
// the same name and nearly the same fields. The server should own it and the frontend import the generated
// one. The generator's own output is that generated one, never a copy.
type MirroredServerTypeDetector struct{}

func (MirroredServerTypeDetector) Sin() sins.Sin {
	return frontend.MirroredServerType{}
}

func (MirroredServerTypeDetector) Find(codebase *engine.Codebase) []engine.Match {
	contracts := published.Of[published.TypeContract](codebase)
	generated := published.Of[published.GeneratedTypes](codebase)

	return typescript.In(codebase).
		WhereObjectType().
		Where(engine.As(func(t typescript.Node) bool { return len(t.FieldNames()) >= mirrorFields })).
		Reject(func(t engine.Match) bool {
			return slices.ContainsFunc(generated, func(g published.GeneratedTypes) bool { return g.Covers(t.File()) })
		}).
		Where(engine.As(func(t typescript.Node) bool {
			return slices.ContainsFunc(contracts, func(c published.TypeContract) bool { return c.MirroredBy(t.Name(), t.FieldNames()) })
		})).
		Get()
}
