package gotree

import (
	"go/ast"
	"go/token"
	"go/types"

	"github.com/jessegall/code-commandments/contract"
)

func (w *writer) readMethods(declaration *ast.TypeSpec, built *contract.Node) {
	object, defined := w.facts.Defs[declaration.Name].(*types.TypeName)
	if !defined || object.IsAlias() {
		return
	}
	var held types.Type = object.Type()
	if contractType, isInterface := held.Underlying().(*types.Interface); isInterface {
		if !contractType.IsMethodSet() {
			return
		}
	} else {
		held = types.NewPointer(held)
	}
	methods := types.NewMethodSet(held)
	if methods.Len() == 0 {
		return
	}
	names := make([]string, 0, methods.Len())
	for at := range methods.Len() {
		selected := methods.At(at)
		method := selected.Obj()
		signature := selected.Type().(*types.Signature)
		shape := types.NewSignatureType(nil, nil, nil, unnamedTuple(signature.Params()), unnamedTuple(signature.Results()), signature.Variadic())
		names = append(names, method.Id()+" "+types.TypeString(shape, func(pkg *types.Package) string { return pkg.Path() }))
	}
	built.Own().Extras = &contract.NodeExtras{Go: &contract.GoNode{Methods: names}}
}

func unnamedTuple(declared *types.Tuple) *types.Tuple {
	variables := make([]*types.Var, 0, declared.Len())
	for at := range declared.Len() {
		variables = append(variables, types.NewVar(token.NoPos, nil, "", declared.At(at).Type()))
	}

	return types.NewTuple(variables...)
}
