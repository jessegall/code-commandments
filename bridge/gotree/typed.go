package gotree

import (
	"go/ast"
	"go/types"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

func (w *writer) typed(held ast.Node, built *contract.Node) {
	if w.facts == nil {
		return
	}

	if expression, isExpression := held.(ast.Expr); isExpression {
		if facts, found := w.facts.Types[expression]; found {
			if at := w.typeIndex(typeOf(facts.Type, "compiler")); at != nil {
				built.ResolvedType = at
			}
			if facts.Value != nil {
				built.Own().Constant = true
			}
		}
	}

	switch node := held.(type) {
	case *ast.Ident:
		w.say(built, func(facts *contract.Facts) { facts.Refers = w.refers(node) })
	case *ast.CallExpr:
		if reached := w.target(node); reached != nil {
			built.Own().Target = reached
		}
	case *ast.FuncDecl:
		w.say(built, func(facts *contract.Facts) { facts.Symbol = w.symbol(node.Name) })
		w.sayType(built, func(facts *contract.Facts) { facts.Returns = w.resultType(node.Type) })
	case *ast.FuncLit:
		w.sayType(built, func(facts *contract.Facts) { facts.Returns = w.resultType(node.Type) })
	case *ast.TypeSpec:
		w.say(built, func(facts *contract.Facts) { facts.Symbol = w.symbol(node.Name) })
	case *ast.ValueSpec:
		w.say(built, func(facts *contract.Facts) { facts.Symbol = w.oneSymbol(node.Names) })
		w.sayType(built, func(facts *contract.Facts) { facts.Declared = w.writtenType(node.Type) })
	case *ast.Field:
		w.say(built, func(facts *contract.Facts) { facts.Symbol = w.oneSymbol(node.Names) })
		w.sayType(built, func(facts *contract.Facts) { facts.Declared = w.writtenType(node.Type) })
	}
}

func (w *writer) say(built *contract.Node, write func(*contract.Facts)) {
	held := &contract.Facts{}
	write(held)
	if held.Symbol == "" && held.Refers == "" {
		return
	}
	write(built.Own())
}

func (w *writer) sayType(built *contract.Node, write func(*contract.Facts)) {
	held := &contract.Facts{}
	write(held)
	if held.Declared == nil && held.Returns == nil {
		return
	}
	write(built.Own())
}

func (w *writer) refers(name *ast.Ident) string {
	object, used := w.facts.Uses[name]
	if !used {
		return ""
	}
	if id := symbolOf(object); id != "" {
		return id
	}

	return w.fieldSymbol(name, object)
}

func (w *writer) symbol(name *ast.Ident) string {
	if name == nil {
		return ""
	}
	object, declared := w.facts.Defs[name]
	if !declared {
		return ""
	}
	if id := symbolOf(object); id != "" {
		return id
	}

	return w.fieldSymbol(name, object)
}

func (w *writer) oneSymbol(names []*ast.Ident) string {
	if len(names) != 1 {
		return ""
	}

	return w.symbol(names[0])
}

func (w *writer) fieldSymbol(name *ast.Ident, object types.Object) string {
	variable, isVariable := object.(*types.Var)
	if !isVariable || !variable.IsField() {
		return ""
	}
	owner := w.owners[name]
	if owner == "" {
		return ""
	}

	return owner + "." + variable.Name()
}

func (w *writer) target(call *ast.CallExpr) *contract.Target {
	named := calleeIdent(call.Fun)
	if named == nil {
		return nil
	}
	object, used := w.facts.Uses[named]
	if !used {
		return w.importedTarget(call)
	}
	function, isFunction := object.(*types.Func)
	if !isFunction {
		return nil
	}
	id := symbolOf(function)
	if id == "" {
		return nil
	}

	reached := &contract.Target{Symbol: id, Name: function.Name()}
	if signature, isSignature := function.Type().(*types.Signature); isSignature && signature.Recv() != nil && function.Pkg() != nil {
		if receiver := receiverName(signature.Recv().Type()); receiver != "" {
			reached.Type = function.Pkg().Path() + "." + receiver
		}
	}

	return reached
}

func (w *writer) importedTarget(call *ast.CallExpr) *contract.Target {
	selector, selects := call.Fun.(*ast.SelectorExpr)
	if !selects {
		return nil
	}
	qualifier, named := selector.X.(*ast.Ident)
	if !named {
		return nil
	}
	imported, isPackage := w.facts.Uses[qualifier].(*types.PkgName)
	if !isPackage {
		return nil
	}

	return &contract.Target{Symbol: imported.Imported().Path() + "." + selector.Sel.Name, Name: selector.Sel.Name}
}

func calleeIdent(callee ast.Expr) *ast.Ident {
	switch held := callee.(type) {
	case *ast.Ident:
		return held
	case *ast.SelectorExpr:
		return held.Sel
	case *ast.IndexExpr:
		return calleeIdent(held.X)
	case *ast.IndexListExpr:
		return calleeIdent(held.X)
	case *ast.ParenExpr:
		return calleeIdent(held.X)
	}

	return nil
}

func (w *writer) writtenType(written ast.Expr) *contract.Type {
	if written == nil {
		return nil
	}
	facts, found := w.facts.Types[written]
	if !found {
		return nil
	}

	return typeOf(facts.Type, "written")
}

func (w *writer) resultType(signature *ast.FuncType) *contract.Type {
	if signature == nil || signature.Results == nil || len(signature.Results.List) == 0 {
		return nil
	}

	var results []*contract.Type
	for _, field := range signature.Results.List {
		written := w.writtenType(field.Type)
		if written == nil {
			return nil
		}
		for range max(1, len(field.Names)) {
			results = append(results, written)
		}
	}
	if len(results) == 1 {
		return results[0]
	}

	return &contract.Type{Text: "(" + textsOf(results) + ")", Kind: "tuple", Members: results, Origin: "written"}
}

func textsOf(types []*contract.Type) string {
	texts := make([]string, 0, len(types))
	for _, held := range types {
		texts = append(texts, held.Text)
	}

	return strings.Join(texts, ", ")
}

func (w *writer) typeIndex(held *contract.Type) *int {
	if held == nil {
		return nil
	}

	key := held.Origin + "\x00" + held.Kind + "\x00" + held.Text
	if at, seen := w.index[key]; seen {
		return &at
	}
	at := len(w.types)
	w.types = append(w.types, held)
	w.index[key] = at

	return &at
}

func ownersIn(parsed *ast.File, facts *types.Info, module string) map[*ast.Ident]string {
	owners := map[*ast.Ident]string{}
	if facts == nil {
		return owners
	}

	for selector, selection := range facts.Selections {
		if selection.Kind() != types.FieldVal {
			continue
		}
		if owner := namedOf(selection.Recv()); owner != "" {
			owners[selector.Sel] = owner
		}
	}

	ast.Inspect(parsed, func(held ast.Node) bool {
		spec, isSpec := held.(*ast.TypeSpec)
		if !isSpec || spec.Name == nil || module == "" {
			return true
		}
		owner := module + "." + spec.Name.Name
		for _, field := range fieldsOf(spec.Type) {
			for _, name := range field.Names {
				owners[name] = owner
			}
		}

		return true
	})

	return owners
}

func fieldsOf(written ast.Expr) []*ast.Field {
	switch shape := written.(type) {
	case *ast.StructType:
		if shape.Fields != nil {
			return shape.Fields.List
		}
	case *ast.InterfaceType:
		if shape.Methods != nil {
			return shape.Methods.List
		}
	}

	return nil
}

func namedOf(held types.Type) string {
	if pointer, isPointer := held.(*types.Pointer); isPointer {
		held = pointer.Elem()
	}
	named, isNamed := held.(*types.Named)
	if !isNamed {
		return ""
	}

	return namedPath(named)
}
