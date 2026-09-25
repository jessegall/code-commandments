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

// DeadEventWiringDetector finds a listener wired to an event the project never raises.
type DeadEventWiringDetector struct{}

func init() { detectors.Register(catalog.Backend, DeadEventWiringDetector{}) }

// Sin is the sin the detector finds.
func (DeadEventWiringDetector) Sin() sins.Sin { return backendsins.DeadEventWiring{} }

// Find is every listener wiring for a declared event class nothing ever builds or dispatches.
func (DeadEventWiringDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n laravelnode.Node) bool { return n.ListenedEventClass() != "" })).
		Where(engine.As(func(n laravelnode.Node) bool {
			_, declared := php.ProgramOf(codebase).Declaration(n.ListenedEventClass())
			return declared
		})).
		Reject(engine.As(func(n laravelnode.Node) bool { return php.ProgramOf(codebase).IsInterface(n.ListenedEventClass()) })).
		Reject(engine.As(func(n laravelnode.Node) bool {
			return php.IsEverProduced(codebase, n.ListenedEventClass(), laravelnode.EventDispatchers...)
		})).
		Get()
}
