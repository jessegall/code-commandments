package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	laravelnode "github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

// OrphanedBindingDetector finds a container binding for an abstract nothing ever resolves.
type OrphanedBindingDetector struct{}

func init() { detectors.Register(catalog.Backend, OrphanedBindingDetector{}) }

// Sin is the sin the detector finds.
func (OrphanedBindingDetector) Sin() sins.Sin { return backendsins.OrphanedBinding{} }

// Find is every binding of an abstract declared here that nothing resolves.
func (OrphanedBindingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n laravelnode.Node) bool { return n.BoundAbstract() != "" })).
		Where(engine.As(func(n laravelnode.Node) bool {
			return laravelnode.ContainerBindingsOf(codebase).IsDeclaredHere(n.BoundAbstract())
		})).
		Reject(engine.As(func(n laravelnode.Node) bool {
			return laravelnode.ContainerBindingsOf(codebase).IsResolvedSomewhere(n.BoundAbstract())
		})).
		Get()
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (OrphanedBindingDetector) WholeTree() {}
