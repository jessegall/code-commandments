package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
)

func init() {
	detectors.Register(catalog.Frontend, ControlFlowOnElementDetector{})
}

// ControlFlowOnElementDetector finds a v-if, v-else-if, v-else or v-for written on a real element instead
// of a <template> wrapper. A <Transition> child keeps its conditional: the transition needs the element itself.
type ControlFlowOnElementDetector struct{}

func (ControlFlowOnElementDetector) Sin() sins.Sin {
	return frontend.ControlFlowOnElement{}
}

func (ControlFlowOnElementDetector) Find(codebase *engine.Codebase) []engine.Match {
	return vue.In(codebase).
		WhereElement().
		Reject(vue.IsTag("template")).
		Reject(vue.IsTag("slot")).
		Where(vue.HasAnyDirective(vue.Structural...)).
		Reject(engine.As(vue.Element.IsTransitionChild)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (ControlFlowOnElementDetector) Repentable() {}
