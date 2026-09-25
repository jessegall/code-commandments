package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, IndexAsKey{})
}

// IndexAsKey is the sin "index-as-key".
type IndexAsKey struct{}

func (IndexAsKey) Definition() sins.Definition {
	return sins.Definition{
		Name:        "index-as-key",
		Skill:       frontendskill.VueControlFlow{},
		Description: "`:key` bound to the `v-for` index — a positional key corrupts state when the list reorders or an item is inserted",
		Rule:        "Key a `v-for` by a stable identity (`:key=\"item.id\"`), never the loop index.",
	}
}
