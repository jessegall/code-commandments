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
	detectors.Register(catalog.Frontend, LoopWithConditionDetector{})
}

// LoopWithConditionDetector finds a v-for and a v-if on one element: Vue gives v-if the higher priority, so
// the condition cannot see the loop's item — filter the list in a computed, or wrap in a <template>.
type LoopWithConditionDetector struct{}

func (LoopWithConditionDetector) Sin() sins.Sin {
	return frontend.LoopWithCondition{}
}

func (LoopWithConditionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return vue.In(codebase).
		WhereElement().
		Where(vue.HasDirective(vue.For)).
		Where(vue.HasAnyDirective(vue.If, vue.ElseIf)).
		Get()
}
