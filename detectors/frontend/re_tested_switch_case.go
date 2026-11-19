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
	detectors.Register(catalog.Vue, RetestedSwitchCaseDetector{})
}

// RetestedSwitchCaseDetector finds an element inside a <SwitchCase> case that tests the switched value again.
type RetestedSwitchCaseDetector struct{}

func (RetestedSwitchCaseDetector) Sin() sins.Sin {
	return frontend.RetestedSwitchCase{}
}

func (RetestedSwitchCaseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return vue.In(codebase).
		WhereElement().
		Where(engine.As(vue.Element.RetestsItsSwitchSubject)).
		Get()
}
