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

// RequestAccessorRecastDetector finds a request's typed string() accessor cast back to a string.
type RequestAccessorRecastDetector struct{}

func init() { detectors.Register(catalog.Backend, RequestAccessorRecastDetector{}) }

// Sin is the sin the detector finds.
func (RequestAccessorRecastDetector) Sin() sins.Sin { return backendsins.RequestAccessorRecast{} }

// Find is every string() sent to a request whose result is made a string again.
func (RequestAccessorRecastDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.MethodCallName() == "string" })).
		Where(engine.As(func(n php.Node) bool { return n.IsUsedOn(laravelnode.RequestTypes...) })).
		Where(engine.As(php.Node.IsReCoercedToString)).
		Get()
}
