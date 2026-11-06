package vue

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the Vue part of a codebase, opening queries over its templates, the twin of the backend Codebase's selectors: vue.In(codebase).WhereElement().Where(...).
type Codebase struct {
	*engine.Codebase
}

func In(codebase *engine.Codebase) *Codebase {
	return &Codebase{Codebase: codebase.Of(contract.Vue)}
}

func (c *Codebase) WhereElement() *engine.Query {
	return c.WhereKind("Element")
}

func (c *Codebase) WhereComponent() *engine.Query {
	return c.WhereKind("Component")
}

func (c *Codebase) Component(path string) (Component, bool) {
	for _, file := range c.Files() {
		if file.Path == path {
			return ComponentOf(file.Match(0)), true
		}
	}

	return Component{}, false
}
