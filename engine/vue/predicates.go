package vue

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Offers(contract.Vue,
		engine.Predicate{Name: "component", Says: "the element is a component, as Vue's compiler resolves its tag: `<Foo>`, `<my-widget>`, `<component :is>`, `<transition>`", Holds: func(m engine.Match) bool { return Element{Match: m}.IsComponent() }},
		engine.Predicate{Name: "templateRoot", Says: "the element is the template's only top-level element", Holds: func(m engine.Match) bool { return Element{Match: m}.IsTemplateRoot() }},
	)
}
