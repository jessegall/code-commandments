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

func init() { detectors.Register(catalog.Backend, DeadConfigKeyDetector{}) }

// Sin is the sin the detector finds.
func (DeadConfigKeyDetector) Sin() sins.Sin { return backendsins.DeadConfigKey{} }

// Find is every config item nothing reads, in a config file something reads from.
func (DeadConfigKeyDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(func(n engine.Match) bool { return laravelnode.ConfigKeysOf(codebase).DeadKeyAt(n) != "" }).
		Get()
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (DeadConfigKeyDetector) CrossFile() {}
