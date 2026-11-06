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

func init() { detectors.Register(catalog.PHP, RequestAccessorRecastDetector{}) }

func (RequestAccessorRecastDetector) Sin() sins.Sin { return backendsins.RequestAccessorRecast{} }

func (RequestAccessorRecastDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.MethodCallName() == "string" })).
		Where(engine.As(func(n php.Node) bool { return n.IsUsedOn(laravelnode.RequestTypes...) })).
		Where(engine.As(php.Node.IsReCoercedToString)).
		Get()
}

func (RequestAccessorRecastDetector) CrossFile() {}
