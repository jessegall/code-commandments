package csharp

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// commentKinds is how a finding on a comment names the comment's kind: `Line comment`, `Documentation comment`.
var commentKinds = map[string]string{"line": "Line", "block": "Block", "doc": "Documentation"}

func init() {
	engine.NameScopes(contract.CSharp, scopeOf)
}

// scopeOf names where a C# finding is: its Roslyn kind, and the name it declares or reads, an accessor answering
// for its property; `MethodDeclaration Total`, `ClassDeclaration Order`; a comment by its kind, `Line comment`.
func scopeOf(match engine.Match) string {
	if match.Kind() == engine.CommentKind {
		return commentKinds[match.Name()] + " comment"
	}
	name := Node{match}.DeclaredName()
	if name == "" {
		return match.Kind()
	}

	return match.Kind() + " " + name
}
