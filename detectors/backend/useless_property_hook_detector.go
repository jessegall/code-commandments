package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// UselessPropertyHookDetector finds a get hook that reads nothing of its object: a constant dressed as state.
type UselessPropertyHookDetector struct{}

func init() { detectors.Register(catalog.PHP, UselessPropertyHookDetector{}) }

func (UselessPropertyHookDetector) Sin() sins.Sin { return backendsins.UselessPropertyHook{} }

func (UselessPropertyHookDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("PropertyHook").
		Where(func(n engine.Match) bool { return n.Name() == "get" }).
		Reject(engine.As(php.Node.IsAbstractHook)).
		Reject(engine.As(php.Node.HookedPropertyHasSetter)).
		Reject(engine.As(php.Node.ReferencesThis)).
		Reject(engine.As(php.Node.IsLateStaticBound)).
		Reject(engine.As(spatie.Node.InDataScope)).
		Get()
}
