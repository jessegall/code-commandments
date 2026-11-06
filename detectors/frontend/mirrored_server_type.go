package frontend

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/published"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
)

func init() {
	detectors.Register(catalog.Vue, MirroredServerTypeDetector{})
}

const mirrorFields = 3

// MirroredServerTypeDetector finds a hand-written TypeScript type that copies a type the server publishes: the same name and nearly the same fields.
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
		Reject(engine.As(func(t typescript.Node) bool {
			return slices.ContainsFunc(contracts, func(c published.TypeContract) bool { return c.GeneratedAs(t.Name(), t.Namespace()) })
		})).
		Where(engine.As(func(t typescript.Node) bool {
			return slices.ContainsFunc(contracts, func(c published.TypeContract) bool { return c.MirroredBy(t.Name(), t.FieldNames()) })
		})).
		Get()
}

func (MirroredServerTypeDetector) CrossFile() {}
