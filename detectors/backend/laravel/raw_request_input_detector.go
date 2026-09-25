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

// RawRequestInputDetector finds request input read raw, where a typed accessor behind a named getter belongs.
type RawRequestInputDetector struct{}

func init() { detectors.Register(catalog.Backend, RawRequestInputDetector{}) }

// Sin is the sin the detector finds.
func (RawRequestInputDetector) Sin() sins.Sin { return backendsins.RawRequestInput{} }

// Find is every input(), get(), query() or post() sent to a request from outside it.
func (RawRequestInputDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool {
			return n.MethodCallName() == "input" || n.MethodCallName() == "get" || n.MethodCallName() == "query" || n.MethodCallName() == "post"
		})).
		Where(engine.As(func(n php.Node) bool { return n.IsUsedOn(laravelnode.RequestTypes...) })).
		Get()
}
