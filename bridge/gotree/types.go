package gotree

import (
	"github.com/jessegall/code-commandments/walk"
	"go/ast"
	"go/parser"
	"go/token"
	"go/types"
	"path/filepath"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// checker type-checks the module's own packages from the sources the run already has, and answers an empty package for anything outside the module.
type checker struct {
	fset     *token.FileSet
	packages map[string]Package
	parsed   map[string][]*ast.File
	checked  map[string]*types.Package
	facts    map[string]*types.Info
	checking map[string]bool
}

func newChecker(fset *token.FileSet, packages []Package) *checker {
	held := &checker{
		fset:     fset,
		packages: map[string]Package{},
		parsed:   map[string][]*ast.File{},
		checked:  map[string]*types.Package{},
		facts:    map[string]*types.Info{},
		checking: map[string]bool{},
	}
	for _, one := range packages {
		if one.Path != "" {
			held.packages[one.Path] = one
		}
	}

	return held
}

func (c *checker) Import(path string) (*types.Package, error) {
	if held, done := c.checked[path]; done {
		return held, nil
	}
	if c.checking[path] {
		return stub(path), nil
	}
	one, mine := c.packages[path]
	if !mine {
		if found, ok := c.packageAt(path); ok {
			one, mine = found, true
		}
	}
	if !mine {
		return stub(path), nil
	}

	c.checking[path] = true
	defer delete(c.checking, path)

	held, _ := c.check(one)
	if held == nil {
		return stub(path), nil
	}

	return held, nil
}

func (c *checker) packageAt(path string) (Package, bool) {
	for _, known := range c.packages {
		module, root := moduleRootOf(known)
		if module == "" || !strings.HasPrefix(path, module) {
			continue
		}
		folder := filepath.Join(root, filepath.FromSlash(strings.TrimPrefix(strings.TrimPrefix(path, module), "/")))
		var files []string
		for _, path := range walk.Entries(folder) {
			name := filepath.Base(path)
			if !strings.HasSuffix(name, ".go") || strings.HasSuffix(name, "_test.go") {
				continue
			}
			if builds(folder, name) {
				files = append(files, path)
			}
		}
		if len(files) == 0 {
			continue
		}
		sort.Strings(files)
		found := Package{Folder: folder, Path: path, Name: packageClause(files[0]), Files: files}
		c.packages[path] = found

		return found, true
	}

	return Package{}, false
}

func moduleRootOf(one Package) (string, string) {
	modules := map[string]Module{}
	module, found := moduleOf(one.Folder, modules)
	if !found {
		return "", ""
	}

	return module.Path, module.Root
}

func (c *checker) check(one Package) (*types.Package, *types.Info) {
	if held, done := c.checked[one.Path]; done {
		return held, c.facts[one.Path]
	}

	files := c.filesOf(one)
	info := &types.Info{
		Types:      map[ast.Expr]types.TypeAndValue{},
		Defs:       map[*ast.Ident]types.Object{},
		Uses:       map[*ast.Ident]types.Object{},
		Selections: map[*ast.SelectorExpr]*types.Selection{},
	}
	config := &types.Config{
		Importer:                 c,
		Error:                    func(error) {},
		DisableUnusedImportCheck: true,
	}
	held, _ := config.Check(importPathOr(one), c.fset, files, info)
	if one.Path != "" {
		c.checked[one.Path] = held
		c.facts[one.Path] = info
	}

	return held, info
}

func (c *checker) filesOf(one Package) []*ast.File {
	if held, done := c.parsed[one.Path]; done && one.Path != "" {
		return held
	}
	var files []*ast.File
	for _, path := range one.Files {
		source, err := read(path)
		if err != nil {
			continue
		}
		parsed, _ := parser.ParseFile(c.fset, path, source, parser.ParseComments|parser.SkipObjectResolution)
		if parsed != nil {
			files = append(files, parsed)
		}
	}
	if one.Path != "" {
		c.parsed[one.Path] = files
	}

	return files
}

func importPathOr(one Package) string {
	if one.Path != "" {
		return one.Path
	}

	return one.Folder
}

func stub(path string) *types.Package {
	name := path
	if at := strings.LastIndex(path, "/"); at >= 0 {
		name = path[at+1:]
	}
	empty := types.NewPackage(path, name)
	empty.MarkComplete()

	return empty
}

func listedTypeOf(held types.Type, origin string) *contract.Type {
	if listed := typeOf(held, origin); listed != nil {
		return listed
	}

	return &contract.Type{Kind: "opaque", Text: types.TypeString(held, qualify), Origin: origin}
}

func typeOf(held types.Type, origin string) *contract.Type {
	if held == nil || held == types.Typ[types.Invalid] {
		return nil
	}
	if tuple, void := held.(*types.Tuple); void && tuple.Len() == 0 {
		return nil
	}

	line := &contract.Type{Text: types.TypeString(held, qualify), Origin: origin}
	switch shape := held.(type) {
	case *types.Basic:
		line.Kind, line.Name, line.ValueType = "keyword", shape.Name(), true
	case *types.Named:
		line.Kind, line.Name = "named", namedPath(shape)
		line.ValueType = isValueType(shape.Underlying())
		line.Nullable = admitsNil(shape.Underlying())
		for i := range shape.TypeArgs().Len() {
			line.Args = append(line.Args, listedTypeOf(shape.TypeArgs().At(i), origin))
		}
		line.Element = elementOf(shape.Underlying(), origin)
	case *types.Pointer:
		line.Kind, line.Nullable, line.Element = "pointer", true, typeOf(shape.Elem(), origin)
	case *types.Slice:
		line.Kind, line.Args, line.Element = "array", []*contract.Type{typeOf(shape.Elem(), origin)}, typeOf(shape.Elem(), origin)
	case *types.Array:
		line.Kind, line.Args, line.Element, line.ValueType = "array", []*contract.Type{typeOf(shape.Elem(), origin)}, typeOf(shape.Elem(), origin), true
	case *types.Map:
		line.Kind, line.Args = "map", []*contract.Type{listedTypeOf(shape.Key(), origin), listedTypeOf(shape.Elem(), origin)}
		line.Element = typeOf(shape.Elem(), origin)
	case *types.Chan:
		line.Kind = "opaque"
	case *types.Struct:
		line.Kind, line.ValueType = "object", true
		for i := range shape.NumFields() {
			field := shape.Field(i)
			line.Fields = append(line.Fields, contract.TypeField{Name: field.Name(), Type: typeOf(field.Type(), origin)})
		}
	case *types.Interface:
		line.Kind, line.Nullable = "opaque", true
	case *types.Signature:
		line.Kind = "function"
		for i := range shape.Params().Len() {
			line.Parameters = append(line.Parameters, listedTypeOf(shape.Params().At(i).Type(), origin))
		}
		line.Returns = resultOf(shape.Results(), origin)
	case *types.Tuple:
		line.Kind = "tuple"
		for i := range shape.Len() {
			line.Members = append(line.Members, listedTypeOf(shape.At(i).Type(), origin))
		}
	case *types.TypeParam:
		line.Kind, line.Name = "parameter", shape.Obj().Name()
	case *types.Union:
		line.Kind = "union"
		for i := range shape.Len() {
			line.Members = append(line.Members, listedTypeOf(shape.Term(i).Type(), origin))
		}
	default:
		line.Kind = "opaque"
	}

	return line
}

func resultOf(results *types.Tuple, origin string) *contract.Type {
	switch {
	case results == nil, results.Len() == 0:
		return nil
	case results.Len() == 1:
		return typeOf(results.At(0).Type(), origin)
	}

	return typeOf(results, origin)
}

func elementOf(held types.Type, origin string) *contract.Type {
	switch shape := held.(type) {
	case *types.Slice:
		return typeOf(shape.Elem(), origin)
	case *types.Array:
		return typeOf(shape.Elem(), origin)
	case *types.Map:
		return typeOf(shape.Elem(), origin)
	case *types.Chan:
		return typeOf(shape.Elem(), origin)
	}

	return nil
}

func isValueType(under types.Type) bool {
	switch under.(type) {
	case *types.Basic, *types.Struct, *types.Array:
		return true
	}

	return false
}

func admitsNil(under types.Type) bool {
	switch under.(type) {
	case *types.Pointer, *types.Interface:
		return true
	}

	return false
}

func namedPath(held *types.Named) string {
	object := held.Obj()
	if object == nil {
		return ""
	}
	if object.Pkg() == nil {
		return object.Name()
	}

	return object.Pkg().Path() + "." + object.Name()
}

func qualify(held *types.Package) string {
	if held == nil {
		return ""
	}

	return held.Path()
}

func symbolOf(object types.Object) string {
	if object == nil || object.Pkg() == nil {
		return ""
	}
	path := object.Pkg().Path()

	switch held := object.(type) {
	case *types.Func:
		if receiver := held.Type().(*types.Signature).Recv(); receiver != nil {
			return path + "." + receiverName(receiver.Type()) + "." + held.Name()
		}
		if held.Parent() != nil && held.Parent().Parent() != types.Universe {
			return ""
		}

		return path + "." + held.Name()
	case *types.TypeName, *types.Const:
		if object.Parent() != nil && object.Parent() != object.Pkg().Scope() {
			return ""
		}

		return path + "." + object.Name()
	case *types.Var:
		if held.IsField() {
			return ""
		}
		if held.Parent() == nil || held.Parent() != object.Pkg().Scope() {
			return ""
		}

		return path + "." + held.Name()
	}

	return ""
}

func receiverName(held types.Type) string {
	if pointer, isPointer := held.(*types.Pointer); isPointer {
		held = pointer.Elem()
	}
	if named, isNamed := held.(*types.Named); isNamed && named.Obj() != nil {
		return named.Obj().Name()
	}

	return ""
}
