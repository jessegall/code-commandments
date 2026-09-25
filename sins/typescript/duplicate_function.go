package typescript

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	typescriptskill "github.com/jessegall/code-commandments/skill/typescript"
)

func init() {
	sins.Register(catalog.TypeScript, DuplicateFunction{})
}

// DuplicateFunction is the sin "duplicate-typescript-function".
type DuplicateFunction struct{}

func (DuplicateFunction) Definition() sins.Definition {
	return sins.Definition{
		Name:        "duplicate-typescript-function",
		Skill:       typescriptskill.Duplication{},
		Description: "Copy-pasted code — two+ TypeScript functions (a `function`, a method, a `const` arrow; in a `.ts` module or a component's script) with an identical body, formatting and comments aside",
		Rule:        "Hoist a function body written twice into one shared function or composable, and call it from both places.",
		Suggestion:  "Move the body to a shared module or composable under one name, and replace every copy with a call to it.",
	}
}
