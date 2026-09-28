package csharp

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// arguments are the expressions a call, object creation or indexer is handed.
func arguments(match engine.Match) []engine.Match {
	var handed []engine.Match
	for _, argument := range (Node{Match: match}).Arguments() {
		handed = append(handed, argument.Match)
	}

	return handed
}

// commentKinds is how a finding on a comment names the comment's kind: `Line comment`, `Documentation comment`.
var commentKinds = map[string]string{"line": "Line", "block": "Block", "doc": "Documentation"}

func init() {
	engine.NameScopes(contract.CSharp, scopeOf)
	engine.RunBodiesAs(contract.CSharp, func(match engine.Match) bool { return Node{Match: match}.FunctionBody().Exists() })
	engine.ListAs(contract.CSharp, engine.Lists{Arguments: arguments, Members: engine.InFields("Members")})
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
