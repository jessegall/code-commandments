package csharp

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

var commentKinds = map[string]string{"line": "Line", "block": "Block", "doc": "Documentation"}

func init() {
	engine.NameScopes(contract.CSharp, scopeOf)
	engine.RunBodiesAs(contract.CSharp, func(match engine.Match) bool { return Node{Match: match}.FunctionBody().Exists() })
}

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
