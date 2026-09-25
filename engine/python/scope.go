package python

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// statements are the name a report gives each kind of Python statement or definition a finding is at.
var statements = map[string]string{
	"AnnAssign":        "AnnAssign",
	"Assign":           "Assign",
	"AugAssign":        "AugAssign",
	"ClassDef":         "ClassDef",
	"FunctionDef":      "FunctionDef",
	"AsyncFunctionDef": "FunctionDef",
	"ExceptHandler":    "ExceptHandler",
	"Expr":             "ExprStmt",
	"For":              "ForLoop",
	"AsyncFor":         "ForLoop",
	"If":               "IfStmt",
	"Import":           "Import",
	"ImportFrom":       "Import",
	"Match":            "MatchStmt",
	"match_case":       "MatchCase",
	"Raise":            "Raise",
	"Return":           "Return_",
	"Try":              "TryStmt",
	"TryStar":          "TryStmt",
	"While":            "WhileLoop",
	"With":             "With",
	"AsyncWith":        "With",
	"arg":              "Param",
	"Break":            "Jump",
	"Continue":         "Jump",
	"Pass":             "Simple",
	"Global":           "Simple",
	"Nonlocal":         "Simple",
	"Delete":           "Simple",
	"Assert":           "Simple",
	"Module":           "Module",
}

// expressions are the name a report gives each kind of Python expression a finding is at.
var expressions = map[string]string{
	"Name":          "name",
	"Constant":      "literal",
	"JoinedStr":     "fstring",
	"Attribute":     "attribute",
	"Subscript":     "subscript",
	"Slice":         "slice",
	"Call":          "call",
	"keyword":       "keyword",
	"Starred":       "starred",
	"Lambda":        "lambda",
	"IfExp":         "conditional",
	"BinOp":         "binary",
	"BoolOp":        "binary",
	"UnaryOp":       "unary",
	"Compare":       "compare",
	"NamedExpr":     "walrus",
	"Tuple":         "tuple",
	"List":          "list",
	"Set":           "set",
	"Dict":          "dict",
	"ListComp":      "comprehension",
	"SetComp":       "comprehension",
	"DictComp":      "comprehension",
	"GeneratorExp":  "comprehension",
	"comprehension": "for",
	"Yield":         "yield",
	"YieldFrom":     "yield",
}

// declaring are the kinds whose finding carries the name they declare.
var declaring = map[string]bool{"ClassDef": true, "FunctionDef": true, "AsyncFunctionDef": true, "arg": true}

func init() {
	engine.NameScopes(contract.Python, scopeOf)
}

// scopeOf names where a Python finding is: a statement by its kind and the name it declares, an
// expression by its kind, and an unpacked `*x` or `**x` as starred.
func scopeOf(match engine.Match) string {
	kind := match.Kind()

	if statement, isStatement := statements[kind]; isStatement {
		if name := declaredName(match); name != "" {
			return statement + " " + name
		}

		return statement
	}

	if slices.Contains(match.Node().Flags, "spread") {
		return "starred"
	}

	if expression, isExpression := expressions[kind]; isExpression {
		return expression
	}

	return "unknown"
}

// declaredName is the first name the node binds: a definition's or a parameter's own, an import's first.
func declaredName(match engine.Match) string {
	kind := match.Kind()

	if declaring[kind] {
		return match.Name()
	}

	if kind != "Import" && kind != "ImportFrom" {
		return ""
	}

	for _, alias := range match.Children() {
		if alias.Kind() != "alias" {
			continue
		}

		if extras := alias.Node().Extras; extras != nil && extras.Python != nil && extras.Python.As != "" {
			return extras.Python.As
		}

		if kind == "Import" {
			return strings.Split(alias.Name(), ".")[0]
		}

		return alias.Name()
	}

	return ""
}
