package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
)

func init() {
	detectors.Register(catalog.Vue, RawFetchInComponentDetector{})
}

// RawFetchInComponentDetector finds a Vue component calling the global fetch itself.
type RawFetchInComponentDetector struct{}

func (RawFetchInComponentDetector) Sin() sins.Sin {
	return frontend.RawFetchInComponent{}
}

func (RawFetchInComponentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return vue.In(codebase).
		WhereKind("CallExpression").
		Where(engine.As(func(n typescript.Node) bool { return n.CallsStandardLibrary("fetch") })).
		Get()
}
