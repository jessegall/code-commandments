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

// DuplicatedConfigDefaultDetector finds a config() read that restates a default its config file already declares.
type DuplicatedConfigDefaultDetector struct{}

func init() { detectors.Register(catalog.Backend, DuplicatedConfigDefaultDetector{}) }

// Sin is the sin the detector finds.
func (DuplicatedConfigDefaultDetector) Sin() sins.Sin { return backendsins.DuplicatedConfigDefault{} }

// Find is every read of a config key with a default argument, where the config file states a default already.
func (DuplicatedConfigDefaultDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(func(n php.Node) bool { _, ok := n.StringArgument(0); return ok })).
		Where(engine.As(func(n php.Node) bool { return n.ArgumentCount() >= 2 })).
		Reject(engine.As(php.Node.ResultIsDiscarded)).
		Where(engine.As(func(n php.Node) bool {
			key, _ := n.StringArgument(0)
			return laravelnode.ConfigKeysOf(codebase).DeclaresDefault(key)
		})).
		Get()
}
