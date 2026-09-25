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
	detectors.Register(catalog.Frontend, DuplicateElementDetector{})
}

// duplicateElements is how many elements a subtree must hold before a copy of it is a component waiting.
const duplicateElements = 3

// DuplicateElementDetector finds markup written twice, the same subtree in any template: one component waiting
// to be extracted. Only the outermost copy is reported, not each repeated piece inside it.
type DuplicateElementDetector struct{}

func (DuplicateElementDetector) Sin() sins.Sin {
	return frontend.DuplicateElement{}
}

func (DuplicateElementDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := vue.In(codebase).
		WhereElement().
		Where(engine.As(func(e vue.Element) bool { return e.Size() >= duplicateElements })).
		Get()
	repeated := repeatedKeys(candidates, structureHash)

	return filtered(candidates, func(element vue.Element) bool {
		return repeated[element.StructureHash()] && !insideRepeat(element, repeated, vue.Element.StructureHash)
	})
}

func structureHash(m engine.Match) string {
	return vue.Of(m).StructureHash()
}

// repeatedKeys is each key two or more of the candidates share.
func repeatedKeys(candidates []engine.Match, key func(engine.Match) string) map[string]bool {
	repeated := map[string]bool{}
	for _, group := range detectors.Recurring(candidates, key) {
		repeated[key(group[0])] = true
	}

	return repeated
}

// insideRepeat says whether an element above it is itself repeated, so the copy is reported there.
func insideRepeat(element vue.Element, repeated map[string]bool, key func(vue.Element) string) bool {
	for above := element.Parent(); above.Exists(); above = above.Parent() {
		if repeated[key(above)] {
			return true
		}
	}

	return false
}

func filtered(candidates []engine.Match, keep func(vue.Element) bool) []engine.Match {
	var kept []engine.Match
	for _, candidate := range candidates {
		if keep(vue.Of(candidate)) {
			kept = append(kept, candidate)
		}
	}

	return kept
}

// Repentable says a scribe rewrites the sin away.
func (DuplicateElementDetector) Repentable() {}
