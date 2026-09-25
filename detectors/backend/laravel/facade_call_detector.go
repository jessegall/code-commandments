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

// FacadeCallDetector finds a Laravel facade called inside a class, where the dependency belongs injected.
type FacadeCallDetector struct{}

func init() { detectors.Register(catalog.Backend, FacadeCallDetector{}) }

// Sin is the sin the detector finds.
func (FacadeCallDetector) Sin() sins.Sin { return backendsins.FacadeCall{} }

// Find is every facade call in a class, save fakes, service providers, Eloquent casts and queued job hooks.
func (FacadeCallDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Expr_StaticCall").
		Where(engine.As(laravelnode.Node.IsFacadeCall)).
		Reject(engine.As(func(n php.Node) bool { return n.StaticCallMethod() == "fake" })).
		Reject(engine.As(func(n php.Node) bool { return !n.IsEnclosedInClass() })).
		Reject(engine.As(laravelnode.Node.InServiceProvider)).
		Reject(engine.As(laravelnode.Node.IsEloquentCast)).
		Reject(engine.As(laravelnode.Node.InQueuedJobHook)).
		Get()
}
