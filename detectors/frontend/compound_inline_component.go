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
	detectors.Register(catalog.Frontend, CompoundInlineComponentDetector{})
}

// compoundBody is how many elements a compound must hold before it is big enough to be its own component.
const compoundBody = 12

// CompoundInlineComponentDetector finds a library compound, a Dialog with its DialogTitle and DialogContent,
// assembled inline in a larger template: a component of its own waiting to be extracted. A compound that is
// already the whole template is that component.
type CompoundInlineComponentDetector struct{}

func (CompoundInlineComponentDetector) Sin() sins.Sin {
	return frontend.CompoundInlineComponent{}
}

func (CompoundInlineComponentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return vue.In(codebase).
		WhereElement().
		Where(engine.As(vue.Element.IsComponent)).
		Where(engine.As(func(e vue.Element) bool { return len(e.CompoundParts()) >= 2 })).
		Where(engine.As(func(e vue.Element) bool { return e.Size() >= compoundBody })).
		Reject(engine.As(vue.Element.IsTemplateRoot)).
		Get()
}

// Repentable says a scribe rewrites the sin away.
func (CompoundInlineComponentDetector) Repentable() {}
