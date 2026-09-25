package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
	"github.com/jessegall/code-commandments/vue"
)

func init() {
	detectors.Register(catalog.Frontend, NearDuplicateElementDetector{})
}

// nearDuplicateElements is how many elements a subtree must hold before a near copy of it is a component waiting.
const nearDuplicateElements = 6

// NearDuplicateElementDetector finds markup written twice with different bindings and text: one component
// taking what differs as props. An exact copy is the duplicate rule's finding, and only the outermost near
// copy is reported.
type NearDuplicateElementDetector struct{}

func (NearDuplicateElementDetector) Sin() sins.Sin {
	return frontend.NearDuplicateElement{}
}

func (NearDuplicateElementDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := vue.In(codebase).
		WhereElement().
		Where(engine.As(func(e vue.Element) bool { return e.Size() >= nearDuplicateElements })).
		Get()
	repeated := repeatedKeys(candidates, shapeHash)
	copies := map[string]int{}
	for _, candidate := range candidates {
		copies[structureHash(candidate)]++
	}

	return filtered(candidates, func(element vue.Element) bool {
		return repeated[element.ShapeHash()] &&
			copies[element.StructureHash()] == 1 &&
			!insideRepeat(element, repeated, vue.Element.ShapeHash)
	})
}

func shapeHash(m engine.Match) string {
	return vue.Of(m).ShapeHash()
}
