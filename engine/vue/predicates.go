package vue

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Predicates(contract.Vue,
		engine.Predicate{Name: "component", Says: "the element's tag names a component: it starts upper-case", Holds: func(m engine.Match) bool { return Element{Match: m}.IsComponent() }},
		engine.Predicate{Name: "templateRoot", Says: "the element is the template's only top-level element", Holds: func(m engine.Match) bool { return Element{Match: m}.IsTemplateRoot() }},
	)
}
