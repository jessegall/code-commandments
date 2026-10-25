package gotree

import (
	"go/ast"
	"go/token"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// roleOf is what the node is in the contract's terms. Go writes a type with the same nodes it writes an
// expression with, so a node is a type because of where it stands, which is what inType carries down.
func roleOf(held ast.Node, inType bool) string {
	switch held.(type) {
	case *ast.File:
		return "other"
	case *ast.FuncDecl, *ast.TypeSpec, *ast.ValueSpec, *ast.Field:
		return "member"
	}
	if inType {
		return "type"
	}
	switch held.(type) {
	case ast.Stmt:
		return "statement"
	case ast.Expr:
		return "expression"
	}

	return "other"
}

// typeFields are the fields whose child is a type as written: everything below one is a type too. A Field's own
// type covers a struct's fields, a function's parameters and its results alike, since Go writes all three as
// field lists.
var typeFields = map[string]bool{"type": true, "key": false, "elt": false}

// isTypeField says whether a child filling this field stands where a type is written.
func isTypeField(field string) bool {
	return typeFields[field]
}

// neutralOf is the language-neutral questions the node answers yes to. A rule that holds for every language asks
// these instead of the kind.
func neutralOf(held ast.Node) []string {
	var is []string
	add := func(neutral string) { is = append(is, neutral) }

	switch node := held.(type) {
	case *ast.FuncDecl, *ast.FuncLit:
		add("function")
	case *ast.TypeSpec:
		add("type-declaration")
	case *ast.BlockStmt:
		add("block")
	case *ast.IfStmt, *ast.SwitchStmt, *ast.TypeSwitchStmt, *ast.SelectStmt:
		add("branch")
	case *ast.ForStmt, *ast.RangeStmt:
		add("loop")
	case *ast.ReturnStmt:
		add("return")
		add("bail-out")
	case *ast.BranchStmt:
		add("bail-out")
	case *ast.ExprStmt:
		add("expression-statement")
	case *ast.CallExpr:
		add("call")
	case *ast.CompositeLit:
		add("construction")
	case *ast.SelectorExpr:
		add("member-access")
	case *ast.Ident:
		add("identifier")
	case *ast.AssignStmt, *ast.IncDecStmt:
		add("assignment")
	case *ast.BasicLit:
		add("literal")
	case *ast.ImportSpec:
		add("import")
	case *ast.GenDecl:
		if node.Tok == token.IMPORT {
			add("import")
		}
	case *ast.BinaryExpr:
		switch node.Op {
		case token.EQL, token.NEQ, token.LSS, token.LEQ, token.GTR, token.GEQ:
			add("comparison")
		case token.LAND, token.LOR:
			add("conjunction")
		}
	}

	return is
}

// nameOf is the name the node carries as written. Go holds a name as an identifier of its own, so a declaration
// carries the name of the identifier it declares as well, which is what every reader of a declaration asks for.
// A declaration of several names at once (`a, b int`) carries none: naming only the first would be a lie.
func nameOf(held ast.Node) string {
	switch node := held.(type) {
	case *ast.Ident:
		return node.Name
	case *ast.FuncDecl:
		return identName(node.Name)
	case *ast.TypeSpec:
		return identName(node.Name)
	case *ast.ValueSpec:
		return oneName(node.Names)
	case *ast.Field:
		return oneName(node.Names)
	case *ast.File:
		return identName(node.Name)
	case *ast.LabeledStmt:
		return identName(node.Label)
	case *ast.SelectorExpr:
		return identName(node.Sel)
	}

	return ""
}

func identName(held *ast.Ident) string {
	if held == nil {
		return ""
	}

	return held.Name
}

// oneName is the name a declaration of exactly one carries.
func oneName(names []*ast.Ident) string {
	if len(names) != 1 {
		return ""
	}

	return identName(names[0])
}

// operatorOf is the operator token the node is written with.
func operatorOf(held ast.Node) string {
	switch node := held.(type) {
	case *ast.BinaryExpr:
		return node.Op.String()
	case *ast.UnaryExpr:
		return node.Op.String()
	case *ast.StarExpr:
		return token.MUL.String()
	case *ast.AssignStmt:
		return node.Tok.String()
	case *ast.IncDecStmt:
		return node.Tok.String()
	case *ast.BranchStmt:
		return node.Tok.String()
	case *ast.GenDecl:
		return node.Tok.String()
	case *ast.ChanType:
		return channelDirection(node)
	}

	return ""
}

// channelDirection is which way a channel type may be used, written as the arrow the source writes.
func channelDirection(held *ast.ChanType) string {
	switch held.Dir {
	case ast.SEND:
		return "chan<-"
	case ast.RECV:
		return "<-chan"
	}

	return "chan"
}

// flagsOf is the syntax facts the node carries that are not keywords.
func flagsOf(held ast.Node) []string {
	switch node := held.(type) {
	case *ast.Field:
		if _, variadic := node.Type.(*ast.Ellipsis); variadic {
			return []string{"variadic"}
		}
	case *ast.CallExpr:
		if node.Ellipsis.IsValid() {
			return []string{"spread"}
		}
	case *ast.FuncDecl:
		return []string{}
	}

	return nil
}

// literalOf is what kind of literal the node is and the value it decodes to. Go writes true, false and nil as
// identifiers rather than literals, so they are not here: what they mean is the type checker's to say, and a
// shadowed `nil` means something else entirely.
func literalOf(held ast.Node) (string, *contract.Value) {
	node, isLiteral := held.(*ast.BasicLit)
	if !isLiteral {
		return "", nil
	}

	switch node.Kind {
	case token.STRING:
		text, err := strconv.Unquote(node.Value)
		if err != nil {
			return "string", nil
		}

		return "string", quoted(text)
	case token.INT:
		return "int", quoted(decimal(node.Value))
	case token.FLOAT:
		return "float", quoted(strings.ReplaceAll(node.Value, "_", ""))
	case token.IMAG:
		return "float", quoted(strings.ReplaceAll(node.Value, "_", ""))
	case token.CHAR:
		// A rune literal is an integer in Go, and its value is the code point it stands for.
		if runes, _, _, err := strconv.UnquoteChar(strings.Trim(node.Value, "'"), '\''); err == nil {
			return "int", quoted(strconv.Itoa(int(runes)))
		}

		return "int", nil
	}

	return "", nil
}
