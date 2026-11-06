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

func init() { detectors.Register(catalog.PHP, NestedTernaryDetector{}) }

func (NestedTernaryDetector) Sin() sins.Sin { return backendsins.NestedTernary{} }

func (NestedTernaryDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsOutermostNestedTernary)).
		Get()
}

func (NestedTernaryDetector) Repentable() {}
