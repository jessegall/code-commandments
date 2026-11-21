package csharp

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func typeDispatch(m engine.Match) bool {
	n := Node{Match: m}
	switched := n.SwitchedTypes()
	seen := map[string]bool{}
	for _, name := range switched {
		seen[name] = true
	}
	if len(seen) < 2 || n.IsTranslatingEveryArm() || n.StoresInOwnState() || n.IsSwitchOverOwnCases() || n.IsWithinNamedConstructor() {
		return false
	}
	program := Of(m.Codebase())
	return program.DeclaresType(n.SwitchedSubjectType()) && !slices.ContainsFunc(switched, func(class string) bool { return !program.DeclaresType(class) })
}
