package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// KeyedLookupEnvyDetector finds a method looking a fact up about another owned object in its own class's keyed store: a question that object should answer.
type KeyedLookupEnvyDetector struct{}

func init() { detectors.Register(catalog.Backend, KeyedLookupEnvyDetector{}) }

// Sin is the sin the detector finds.
func (KeyedLookupEnvyDetector) Sin() sins.Sin { return backendsins.KeyedLookupEnvy{} }

// Find is every method that envies another owned class through a keyed lookup.
func (KeyedLookupEnvyDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_ClassMethod").
		Where(func(n engine.Match) bool { return php.LookupEnvyOf(codebase).IsEnviedOwner(n) }).
		Get()
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (KeyedLookupEnvyDetector) WholeTree() {}
