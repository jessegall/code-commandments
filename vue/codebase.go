package vue

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the Vue part of a codebase, opening queries over its templates, the twin of the backend
// Codebase's selectors: vue.In(codebase).WhereElement().Where(...).
type Codebase struct {
	*engine.Codebase
}

// In is the Vue part of the codebase: its .vue files.
func In(codebase *engine.Codebase) *Codebase {
	return &Codebase{Codebase: codebase.Of(contract.Vue)}
}

// WhereElement opens a query over every template element.
func (c *Codebase) WhereElement() *engine.Query {
	return c.WhereKind("Element")
}
