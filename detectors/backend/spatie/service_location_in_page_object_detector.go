package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	spatienode "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/spatie"
)

// ServiceLocationInPageObjectDetector finds a page object resolving a service from the container by hand.
type ServiceLocationInPageObjectDetector struct{}

func init() { detectors.Register(catalog.Backend, ServiceLocationInPageObjectDetector{}) }

// Sin is the sin the detector finds.
func (ServiceLocationInPageObjectDetector) Sin() sins.Sin {
	return backendsins.ServiceLocationInPageObject{}
}

// Find is every app() or resolve() of a class in a page object.
func (ServiceLocationInPageObjectDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { return n.CallsFunction("app") || n.CallsFunction("resolve") })).
		Where(engine.As(php.Node.FirstArgIsClassLiteral)).
		Where(engine.As(spatienode.Node.IsPageObject)).
		Get()
}
