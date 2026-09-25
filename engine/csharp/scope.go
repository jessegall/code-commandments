package csharp

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.NameScopes(contract.CSharp, scopeOf)
}

// scopeOf names where a C# finding is: its Roslyn kind, and the name it declares or reads, an accessor answering
// for its property; `MethodDeclaration Total`, `ClassDeclaration Order`.
func scopeOf(match engine.Match) string {
	name := Node{match}.DeclaredName()
	if name == "" {
		return match.Kind()
	}

	return match.Kind() + " " + name
}
