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
	detectors.Register(catalog.Frontend, InlineCaseViewsDetector{})
}

// viewCases is how many of a dispatch's cases must each render a whole view before the dispatch is doing their
// jobs itself.
const viewCases = 2

// InlineCaseViewsDetector finds a dispatch on a value, a <SwitchCase> or a v-if chain re-testing one subject, whose
// cases each render a whole view inline: one component doing a job per case, where each case wants a component of
// its own and the dispatch only picks one.
type InlineCaseViewsDetector struct{}

func (InlineCaseViewsDetector) Sin() sins.Sin {
	return frontend.InlineCaseViews{}
}

func (InlineCaseViewsDetector) Find(codebase *engine.Codebase) []engine.Match {
	return vue.In(codebase).
		WhereElement().
		Where(engine.As(vue.Element.IsDispatch)).
		Where(engine.As(func(e vue.Element) bool { return len(e.ViewCases()) >= viewCases })).
		Get()
}
