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

func init() { detectors.Register(catalog.Backend, UselessPropertyHookDetector{}) }

// Sin is the sin the detector finds.
func (UselessPropertyHookDetector) Sin() sins.Sin { return backendsins.UselessPropertyHook{} }

// Find is every concrete get hook on a property without a setter that reaches neither $this, self, static nor parent, outside Data classes.
func (UselessPropertyHookDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereKind("PropertyHook").
		Where(func(n engine.Match) bool { return n.Name() == "get" }).
		Reject(engine.As(php.Node.IsAbstractHook)).
		Reject(engine.As(php.Node.HookedPropertyHasSetter)).
		Reject(engine.As(php.Node.ReferencesThis)).
		Reject(engine.As(php.Node.IsLateStaticBound)).
		Reject(engine.As(spatie.Node.InDataScope)).
		Get()
}
