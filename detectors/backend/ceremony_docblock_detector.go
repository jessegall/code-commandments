package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// CeremonyDocblockDetector finds a method docblock that only restates the types its signature declares.
type CeremonyDocblockDetector struct{}

func init() { detectors.Register(catalog.Backend, CeremonyDocblockDetector{}) }

// Sin is the sin the detector finds.
func (CeremonyDocblockDetector) Sin() sins.Sin { return backendsins.CeremonyDocblock{} }

// Find is every method whose doc comment only restates its native types.
func (CeremonyDocblockDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereKind("Stmt_ClassMethod").
		Where(engine.As(php.Node.HasCeremonyDocblock)).
		Get()
}
