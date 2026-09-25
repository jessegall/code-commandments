package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// MutableStaticStateDetector finds a write to static state, which outlives the request and leaks between callers.
type MutableStaticStateDetector struct{}

func init() { detectors.Register(catalog.Backend, MutableStaticStateDetector{}) }

// Sin is the sin the detector finds.
func (MutableStaticStateDetector) Sin() sins.Sin { return backendsins.MutableStaticState{} }

// Find is every write to a static property, save a memo filled after a presence test and a static method answering to an ancestor's.
func (MutableStaticStateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsStaticStateWrite)).
		Reject(engine.As(func(n php.Node) bool {
			return n.IsInStaticMethod() && php.ProgramOf(codebase).OverridesMethod(php.EnclosingClassName(n.Match), php.EnclosingFunctionName(n.Match))
		})).
		Get()
}
