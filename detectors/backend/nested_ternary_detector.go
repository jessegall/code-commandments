package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// NestedTernaryDetector finds a ternary with another ternary inside a branch.
type NestedTernaryDetector struct{}

func init() { detectors.Register(catalog.Backend, NestedTernaryDetector{}) }

// Sin is the sin the detector finds.
func (NestedTernaryDetector) Sin() sins.Sin { return backendsins.NestedTernary{} }

// Find is every outermost ternary with a ternary in a branch.
func (NestedTernaryDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsOutermostNestedTernary)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (NestedTernaryDetector) Repentable() {}
