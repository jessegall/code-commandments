package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// FeatureEnvyDetector finds a method doing another class's work: querying its collection, or looping its structure
// or writing its fields through a parameter more than it reaches into its own class.
type FeatureEnvyDetector struct{}

func init() { detectors.Register(catalog.Backend, FeatureEnvyDetector{}) }

// Sin is the sin the detector finds.
func (FeatureEnvyDetector) Sin() sins.Sin { return backendsins.FeatureEnvy{} }

// Exemptions lets a package declare its framework entry points, which a method may unpack without envy.
func (FeatureEnvyDetector) Exemptions() []packages.Exemption {
	return []packages.Exemption{{Tag: packages.Boundary}}
}

// Find is every method that envies another class the project owns.
func (FeatureEnvyDetector) Find(codebase *engine.Codebase) []engine.Match {
	envy := php.EnvyOf(codebase)
	boundary := func(class string) bool { return packages.Excuses(codebase, packages.Boundary, class, "") }

	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Where(func(n engine.Match) bool { return envy.IsEnviedOwner(n, boundary) }).
		Get()
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (FeatureEnvyDetector) CrossFile() {}
