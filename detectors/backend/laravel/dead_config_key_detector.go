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

// DeadConfigKeyDetector finds a key a config file declares that nothing in the project reads.
type DeadConfigKeyDetector struct{}

func init() { detectors.Register(catalog.PHP, DeadConfigKeyDetector{}) }

func (DeadConfigKeyDetector) Sin() sins.Sin { return backendsins.DeadConfigKey{} }

func (DeadConfigKeyDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(func(n engine.Match) bool { return laravelnode.ConfigKeysOf(codebase).DeadKeyAt(n) != "" }).
		Get()
}

func (DeadConfigKeyDetector) CrossFile() {}
