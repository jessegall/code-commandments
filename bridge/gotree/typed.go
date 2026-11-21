package gotree

import (
	"go/ast"
	"go/types"

	"github.com/jessegall/code-commandments/contract"
)

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
	if function.Pkg() != nil {
		reached.Type = function.Pkg().Path()
	}
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

	return &contract.Target{Symbol: imported.Imported().Path() + "." + selector.Sel.Name, Type: imported.Imported().Path(), Name: selector.Sel.Name}
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
