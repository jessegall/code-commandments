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

// ModelMutationAtCallSiteDetector finds a model whose properties a caller sets before saving it, where an intention-revealing method on the model belongs.
type ModelMutationAtCallSiteDetector struct{}

func init() { detectors.Register(catalog.Backend, ModelMutationAtCallSiteDetector{}) }

// Sin is the sin the detector finds.
func (ModelMutationAtCallSiteDetector) Sin() sins.Sin { return backendsins.ModelMutationAtCallSite{} }

// Find is every save() of a model whose properties its function writes.
func (ModelMutationAtCallSiteDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.MethodCallName() == "save" })).
		Where(engine.As(php.Node.ReceiverMutatedNearby)).
		Where(engine.As(laravelnode.Node.ReceiverIsModel)).
		Get()
}
