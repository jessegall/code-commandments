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
	detectors.Register(catalog.Frontend, SwitchCaseDetector{})
}

// SwitchCaseDetector finds a v-if/v-else-if chain that re-tests one subject against a literal case by case:
// a dispatch on a value, which <SwitchCase :value> writes as one decision.
type SwitchCaseDetector struct{}

func (SwitchCaseDetector) Sin() sins.Sin {
	return frontend.SwitchCase{}
}

func (SwitchCaseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return vue.In(codebase).
		WhereElement().
		Where(vue.HasDirective(vue.If)).
		Where(engine.As(vue.Element.HeadsSwitchCase)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (SwitchCaseDetector) Repentable() {}
