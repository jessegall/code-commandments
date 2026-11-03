package gotree

import (
	"go/ast"
	"reflect"
	"runtime"
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

// nodes are every node type go/ast has. Go has no registry of its own types, so the list is written here, beside
// the writer that names them: a kind is the type's name and a field is one of its own struct fields, so the rest
// of the declaration is read off each type rather than written out. A type Go adds and this list misses is caught
// by the test that holds the declaration to what the streams really carry.
//
// A comment is no node here: the contract keeps a file's comments beside its tree, not in it.
var nodes = []ast.Node{
	&ast.ArrayType{}, &ast.AssignStmt{}, &ast.BadDecl{}, &ast.BadExpr{}, &ast.BadStmt{}, &ast.BasicLit{},
	&ast.BinaryExpr{}, &ast.BlockStmt{}, &ast.BranchStmt{}, &ast.CallExpr{}, &ast.CaseClause{}, &ast.ChanType{},
	&ast.CommClause{}, &ast.CompositeLit{}, &ast.DeclStmt{}, &ast.DeferStmt{}, &ast.Ellipsis{}, &ast.EmptyStmt{},
	&ast.ExprStmt{}, &ast.Field{}, &ast.FieldList{}, &ast.File{}, &ast.ForStmt{}, &ast.FuncDecl{},
	&ast.FuncLit{}, &ast.FuncType{}, &ast.GenDecl{}, &ast.GoStmt{}, &ast.Ident{}, &ast.IfStmt{},
	&ast.ImportSpec{}, &ast.IncDecStmt{}, &ast.IndexExpr{}, &ast.IndexListExpr{}, &ast.InterfaceType{},
	&ast.KeyValueExpr{}, &ast.LabeledStmt{}, &ast.MapType{}, &ast.ParenExpr{}, &ast.RangeStmt{},
	&ast.ReturnStmt{}, &ast.SelectStmt{}, &ast.SelectorExpr{}, &ast.SendStmt{}, &ast.SliceExpr{},
	&ast.StarExpr{}, &ast.StructType{}, &ast.SwitchStmt{}, &ast.TypeAssertExpr{}, &ast.TypeSpec{},
	&ast.TypeSwitchStmt{}, &ast.UnaryExpr{}, &ast.ValueSpec{},
}

// Declares is what this bridge can emit. Go is parsed in this process, so there is no bridge to run for it: the
// answer is read straight off go/ast with the SAME readings the writer makes — kindOf for the kind, and the
// exported struct fields that can hold a node, named as lowerFirst names them and skipped where the writer skips
// them — so the declaration cannot drift from the stream.
func Declares() contract.Declaration {
	declared := make([]contract.DeclaredKind, 0, len(nodes))
	for _, held := range nodes {
		declared = append(declared, contract.DeclaredKind{Kind: kindOf(held), Fields: declaredFields(held)})
	}

	slices.SortFunc(declared, func(one, other contract.DeclaredKind) int {
		switch {
		case one.Kind < other.Kind:
			return -1
		case one.Kind > other.Kind:
			return 1
		}

		return 0
	})

	return contract.Declaration{
		Language: contract.Go,
		Parser:   "go/ast of " + runtime.Version(),
		From:     "every node type go/ast has, listed in this bridge beside its writer, each one's kind and fields read off the type with the same readings the writer makes",
		Kinds:    declared,
	}
}

// declaredFields are the slots a child of the node fills: its own exported struct fields that can hold a node, named as
// the writer names them.
func declaredFields(held ast.Node) []string {
	structure := reflect.TypeOf(held).Elem()
	var fields []string
	for i := range structure.NumField() {
		field := structure.Field(i)
		if !field.IsExported() || skippedField(held, field.Name) || !holdsANode(field.Type) {
			continue
		}
		fields = append(fields, lowerFirst(field.Name))
	}
	slices.Sort(fields)

	return fields
}

// holdsANode says whether a struct field can hold a child: a node, or a list of them. A comment field is already
// skipped by name, as the writer skips it.
func holdsANode(field reflect.Type) bool {
	if field.Kind() == reflect.Slice {
		return holdsANode(field.Elem())
	}

	node := reflect.TypeOf((*ast.Node)(nil)).Elem()

	return field.Implements(node) || (field.Kind() == reflect.Interface && node.Implements(field))
}
