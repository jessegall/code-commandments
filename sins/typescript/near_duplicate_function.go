package typescript

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	typescriptskill "github.com/jessegall/code-commandments/skill/typescript"
)

func init() {
	sins.Register(catalog.TypeScript, NearDuplicateFunction{})
}

// NearDuplicateFunction is the sin "near-duplicate-typescript-function".
type NearDuplicateFunction struct{}

func (NearDuplicateFunction) Definition() sins.Definition {
	return sins.Definition{
		Name:        "near-duplicate-typescript-function",
		Skill:       typescriptskill.Duplication{},
		Description: "A near-copy — two+ TypeScript functions with one control-flow skeleton that differ only in their local names or the literals they use (an endpoint, a key, a label)",
		Rule:        "Merge two functions that differ only in a literal into one, and pass what differs as a parameter.",
		Suggestion:  "Name the literal that differs, make it a parameter of one shared function, and call that from both places.",
	}
}
