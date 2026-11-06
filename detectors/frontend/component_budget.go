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
	detectors.Register(catalog.Vue, ComponentBudgetDetector{})
}

// ComponentBudgetDetector finds a component whose template renders more elements than the project's declared budget: how many jobs one component may hold is the project's own call, so the rule judges nothing until the project declares one.
type ComponentBudgetDetector struct {
	Budget int
}

func (ComponentBudgetDetector) Sin() sins.Sin {
	return frontend.OversizedComponent{}
}

func (d ComponentBudgetDetector) Elements(budget int) ComponentBudgetDetector {
	d.Budget = budget

	return d
}

func (d ComponentBudgetDetector) Find(codebase *engine.Codebase) []engine.Match {
	if d.Budget == 0 {
		return nil
	}
	oversized := vue.In(codebase).
		WhereComponent().
		Where(engine.As(func(c vue.Component) bool { return c.Size() > d.Budget })).
		Get()
	found := make([]engine.Match, 0, len(oversized))
	for _, component := range oversized {
		found = append(found, vue.ComponentOf(component).TemplateElements()[0].Match)
	}

	return found
}
