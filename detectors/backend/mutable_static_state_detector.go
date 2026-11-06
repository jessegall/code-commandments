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

func init() { detectors.Register(catalog.PHP, MutableStaticStateDetector{}) }

func (MutableStaticStateDetector) Sin() sins.Sin { return backendsins.MutableStaticState{} }

func (MutableStaticStateDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsStaticStateWrite)).
		Reject(engine.As(func(n php.Node) bool {
			return n.IsInStaticMethod() && php.ProgramOf(codebase).OverridesMethod(php.EnclosingClassName(n.Match), php.EnclosingFunctionName(n.Match))
		})).
		Reject(engine.Match.IsTest).
		Get()
}
