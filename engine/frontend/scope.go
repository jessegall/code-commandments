package frontend

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// declarations are the name a report gives each kind of TypeScript declaration a finding is at.
var declarations = map[string]string{
	"FunctionDeclaration": "FunctionDecl",
	"FunctionExpression":  "FunctionDecl",
	"ClassDeclaration":    "ClassDecl",
	"Parameter":           "Param",
	"MethodDeclaration":   "MethodDecl",
	"Constructor":         "MethodDecl",
	"GetAccessor":         "MethodDecl",
	"SetAccessor":         "MethodDecl",
	"SourceFile":          "Module",
	"CallExpression":      "CallExpr",
	"ImportDeclaration":   "ImportDecl",
	"VariableDeclaration": "VariableDecl",
	"PropertyDeclaration": "FieldDecl",
	"PropertySignature":   "FieldDecl",
}

// typeDeclarations are the kinds a finding names as the type they declare.
var typeDeclarations = map[string]bool{"InterfaceDeclaration": true, "TypeAliasDeclaration": true}

func init() {
	engine.NameScopes(contract.Vue, scopeOf)
	engine.NameScopes(contract.TypeScript, scopeOf)
}

// scopeOf names where a frontend finding is: an element by its tag, a type by its name, a declaration by
// its kind and name, and an expression by what it says.
func scopeOf(match engine.Match) string {
	kind := match.Kind()

	if kind == "Element" {
		return "<" + match.Name() + ">"
	}

	if typeDeclarations[kind] {
		return "type " + match.Name()
	}

	if declared, isDeclaration := declarations[kind]; isDeclaration {
		return named(declared, match.Name())
	}

	if match.Node().Role == "expression" {
		return match.Written()
	}

	return named(kind, match.Name())
}

func named(kind, name string) string {
	if name == "" {
		return kind
	}

	return kind + " " + name
}
