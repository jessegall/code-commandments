package gotree

import (
	"go/ast"
	"go/types"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// typed writes onto the node what only the checker can know: the type an expression has, whether the compiler
// folds it to a constant, the declaration a name refers to, the declaration a call reaches, the type a
// declaration writes, and the id of the declaration itself.
//
// Every one of them is absent where the checker did not give it. A type reached through an import outside the
// module is such a case, by the decision doc 3 holds, and no rule reads a type that is not there.
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

// say writes a fact onto the node, and leaves the node sharing the empty facts where there is nothing to say: a
// node with no fact of its own must never be given one, since every such node shares the one empty value, and a
// fact written through it would land on all of them at once.
func (w *writer) say(built *contract.Node, write func(*contract.Facts)) {
	held := &contract.Facts{}
	write(held)
	if held.Symbol == "" && held.Refers == "" {
		return
	}
	write(built.Own())
}

// sayType is say for a fact that is a type.
func (w *writer) sayType(built *contract.Node, write func(*contract.Facts)) {
	held := &contract.Facts{}
	write(held)
	if held.Declared == nil && held.Returns == nil {
		return
	}
	write(built.Own())
}

// refers is the id of the declaration a name names, where the name is a use of one that can be reached by id.
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

// symbol is the id of the declaration a name declares.
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

// oneSymbol is the id of a declaration that declares exactly one name; a declaration of several at once carries
// none, as its name does.
func (w *writer) oneSymbol(names []*ast.Ident) string {
	if len(names) != 1 {
		return ""
	}

	return w.symbol(names[0])
}

// fieldSymbol is the id of a struct's field, which is the type it belongs to with the field's own name after it.
// The checker's object for a field does not say which type holds it, so the owner is read from the file once:
// from the selection a read of the field was reached by, and from the declaration a field was declared in.
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

// target is the declaration a call reaches: the function or method it names, with the type that declares it.
// A call of something the checker could not resolve, or of a value rather than a declaration, reaches nothing.
func (w *writer) target(call *ast.CallExpr) *contract.Target {
	named := calleeIdent(call.Fun)
	if named == nil {
		return nil
	}
	object, used := w.facts.Uses[named]
	if !used {
		return nil
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

// calleeIdent is the name a call calls: the function's own, or the member a selector reaches.
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

// writtenType is the type a declaration writes, as the source wrote it.
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

// resultType is what a function says it answers: the one type it returns, or the tuple of several. A function
// that returns nothing says nothing.
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

// textsOf is the types' own texts, comma separated, which is how Go spells a list of results.
func textsOf(types []*contract.Type) string {
	texts := make([]string, 0, len(types))
	for _, held := range types {
		texts = append(texts, held.Text)
	}

	return strings.Join(texts, ", ")
}

// typeIndex is where the file says that type, adding it to the file's list the first time it is seen: a type
// every expression shares is written once and named by its place, which is what keeps a large file's line from
// repeating one type thousands of times.
func (w *writer) typeIndex(held *contract.Type) *int {
	if held == nil {
		return nil
	}

	// A type's text is canonical and names every package it mentions by path, so the text, its shape and where
	// it came from name the type: two types that agree on all three are one type. Marshalling each one to
	// compare it would spend an encoding on every typed expression in the tree, hundreds of thousands of them.
	key := held.Origin + "\x00" + held.Kind + "\x00" + held.Text
	if at, seen := w.index[key]; seen {
		return &at
	}
	at := len(w.types)
	w.types = append(w.types, held)
	w.index[key] = at

	return &at
}

// ownersIn is the id of the type that declares each field name the file writes, read from the checker: a field
// declared in a struct belongs to the type being declared, and a field read through a receiver belongs to the
// type that receiver has. Without it a field's id would depend on where in the file it is named, and a read of
// `c.lines` would carry no id at all.
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

// fieldsOf are the fields a type declaration declares, of a struct or of an interface.
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

// namedOf is the id of the named type a value has, a pointer read through to the type itself.
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
