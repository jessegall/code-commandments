package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// HandRolledRegistryDetector finds a concrete class writing its own keyed-store plumbing.
type HandRolledRegistryDetector struct{}

func init() { detectors.Register(catalog.PHP, HandRolledRegistryDetector{}) }

func (HandRolledRegistryDetector) Sin() sins.Sin { return backendsins.HandRolledRegistry{} }

func (HandRolledRegistryDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(php.Node.HandRollsAKeyedStore)).
		Reject(engine.Match.IsTest).
		Get()
}
