package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
)

func init() {
	detectors.Register(catalog.Frontend, &DeepNestedDetector{MaxDepth: 8, MaxRemaining: 3})
}

// DeepNestedDetector finds markup nested too deep to read, where enough still lies beneath to be a component
// of its own. Each is reported once, at the boundary it would be extracted from.
type DeepNestedDetector struct {
	// MaxDepth is how deep an element may sit before it is too deep.
	MaxDepth int
	// MaxRemaining is how many levels must still lie beneath a too-deep element for it to be worth extracting.
	MaxRemaining int
}

func (*DeepNestedDetector) Sin() sins.Sin {
	return frontend.DeepNested{}
}

func (d *DeepNestedDetector) Find(codebase *engine.Codebase) []engine.Match {
	tooDeep := vue.In(codebase).
		WhereElement().
		Where(engine.As(func(e vue.Element) bool { return e.Depth() > d.MaxDepth })).
		Where(engine.As(func(e vue.Element) bool { return e.Height()-1 > d.MaxRemaining })).
		Get()
	var boundaries []engine.Match
	seen := map[*contract.Node]bool{}
	for _, element := range tooDeep {
		boundary := vue.Of(element).Boundary()
		if !boundary.IsExtractable() || seen[boundary.Node()] {
			continue
		}
		seen[boundary.Node()] = true
		boundaries = append(boundaries, boundary.Match)
	}

	return boundaries
}

// Repentable says a scribe rewrites the sin away.
func (DeepNestedDetector) Repentable() {}
