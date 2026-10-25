package gotree

import (
	"go/ast"
	"go/parser"
	"go/token"
	"go/types"
	"os"
	"path/filepath"
	"sort"
	"strings"
)

// checker type-checks the module's own packages from the sources the run already has, and answers an empty
// package for anything outside the module.
//
// That is the whole of how Go is typed here, and doc 3 holds the measurement it was decided by: typing the
// module's own sources gives 86 percent of the expressions a full typing gives and 89 percent of its
// cross-package types, in a fortieth of the time, with no toolchain installed. What it loses is every type
// reached through an import outside the module, which is written as nothing at all rather than guessed, so no
// rule reads a type the checker did not give.
type checker struct {
	fset     *token.FileSet
	packages map[string]Package
	parsed   map[string][]*ast.File
	checked  map[string]*types.Package
	facts    map[string]*types.Info
	checking map[string]bool
}

// newChecker holds the packages a run was handed, ready to be checked on demand.
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

// Import is what the type checker asks of an import. A package of this module is checked from its own sources,
// and anything else is an empty package: the standard library and every third-party module arrive as nothing, so
// what they declare is unknown rather than wrong.
func (c *checker) Import(path string) (*types.Package, error) {
	if held, done := c.checked[path]; done {
		return held, nil
	}
	if c.checking[path] {
		// Go forbids an import cycle, so this is a cycle only a broken tree can hold: it is left unresolved
		// rather than followed for ever.
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

// packageAt is a package of this module the run was not handed: a sibling of a file it was, which the folder
// beside it holds. A run over one file still resolves its own package's siblings this way, which is what the
// hooks ask for when they judge the file an agent has just written.
func (c *checker) packageAt(path string) (Package, bool) {
	for _, known := range c.packages {
		module, root := moduleRootOf(known)
		if module == "" || !strings.HasPrefix(path, module) {
			continue
		}
		folder := filepath.Join(root, filepath.FromSlash(strings.TrimPrefix(strings.TrimPrefix(path, module), "/")))
		entries, err := os.ReadDir(folder)
		if err != nil {
			continue
		}
		var files []string
		for _, entry := range entries {
			if entry.IsDir() || !strings.HasSuffix(entry.Name(), ".go") || strings.HasSuffix(entry.Name(), "_test.go") {
				continue
			}
			if builds(folder, entry.Name()) {
				files = append(files, filepath.Join(folder, entry.Name()))
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

// moduleRootOf is the module path a package belongs to and the folder that module's go.mod sits in.
func moduleRootOf(one Package) (string, string) {
	modules := map[string]Module{}
	module, found := moduleOf(one.Folder, modules)
	if !found {
		return "", ""
	}

	return module.Path, module.Root
}

// check type-checks one package, parsing its files once and keeping both what it parsed and what the checker
// learned, since the facts are keyed by the very nodes the tree is written from.
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

// filesOf are the package's parsed files, parsed once: the tree is written from these very nodes, so the facts
// the checker learned about them are the facts the tree carries.
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

// importPathOr is the path a package is checked under: a folder outside any module is checked under its own
// folder, so the checker still has one name for it.
func importPathOr(one Package) string {
	if one.Path != "" {
		return one.Path
	}

	return one.Folder
}

// stub is an empty package standing for one outside the module, so the checker carries on and anything reached
// through it has no type rather than stopping the check.
func stub(path string) *types.Package {
	name := path
	if at := strings.LastIndex(path, "/"); at >= 0 {
		name = path[at+1:]
	}
	empty := types.NewPackage(path, name)
	empty.MarkComplete()

	return empty
}

// typeOf is the contract's type for what the checker gave, with origin saying where it came from. Nothing is
// invented: a type the checker did not give is no type at all.
func typeOf(held types.Type, origin string) *typeLine {
	if held == nil || held == types.Typ[types.Invalid] {
		return nil
	}

	line := &typeLine{Text: types.TypeString(held, qualify), Origin: origin}
	switch shape := held.(type) {
	case *types.Basic:
		line.Kind, line.Name, line.ValueType = "keyword", shape.Name(), true
	case *types.Named:
		line.Kind, line.Name = "named", namedPath(shape)
		line.ValueType = isValueType(shape.Underlying())
		line.Nullable = admitsNil(shape.Underlying())
		for i := range shape.TypeArgs().Len() {
			line.Args = append(line.Args, typeOf(shape.TypeArgs().At(i), origin))
		}
		line.Element = elementOf(shape.Underlying(), origin)
	case *types.Pointer:
		line.Kind, line.Nullable = "opaque", true
	case *types.Slice:
		line.Kind, line.Args, line.Element = "array", []*typeLine{typeOf(shape.Elem(), origin)}, typeOf(shape.Elem(), origin)
	case *types.Array:
		line.Kind, line.Args, line.Element, line.ValueType = "array", []*typeLine{typeOf(shape.Elem(), origin)}, typeOf(shape.Elem(), origin), true
	case *types.Map:
		line.Kind = "opaque"
	case *types.Chan:
		line.Kind = "opaque"
	case *types.Struct:
		line.Kind, line.ValueType = "object", true
		for i := range shape.NumFields() {
			field := shape.Field(i)
			line.Fields = append(line.Fields, typeField{Name: field.Name(), Type: typeOf(field.Type(), origin)})
		}
	case *types.Interface:
		line.Kind, line.Nullable = "opaque", true
	case *types.Signature:
		line.Kind = "function"
		for i := range shape.Params().Len() {
			line.Parameters = append(line.Parameters, typeOf(shape.Params().At(i).Type(), origin))
		}
		line.Returns = resultOf(shape.Results(), origin)
	case *types.Tuple:
		line.Kind = "tuple"
		for i := range shape.Len() {
			line.Members = append(line.Members, typeOf(shape.At(i).Type(), origin))
		}
	case *types.TypeParam:
		line.Kind, line.Name = "parameter", shape.Obj().Name()
	case *types.Union:
		line.Kind = "union"
		for i := range shape.Len() {
			line.Members = append(line.Members, typeOf(shape.Term(i).Type(), origin))
		}
	default:
		line.Kind = "opaque"
	}

	return line
}

// resultOf is what a function answers: the one type it returns, the tuple of several, and nothing where it
// returns nothing at all.
func resultOf(results *types.Tuple, origin string) *typeLine {
	switch {
	case results == nil, results.Len() == 0:
		return nil
	case results.Len() == 1:
		return typeOf(results.At(0).Type(), origin)
	}

	return typeOf(results, origin)
}

// elementOf is the element of a collection, where the type is one: it is what a rule about a bag of values reads.
func elementOf(held types.Type, origin string) *typeLine {
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

// isValueType says whether a value of the type is copied rather than shared, which a struct, an array and every
// basic type are in Go.
func isValueType(under types.Type) bool {
	switch under.(type) {
	case *types.Basic, *types.Struct, *types.Array:
		return true
	}

	return false
}

// admitsNil says whether the type admits absence. A pointer and an interface do, and that is all: a nil slice
// ranges cleanly and a nil map reads, so neither stands for a value that is missing.
func admitsNil(under types.Type) bool {
	switch under.(type) {
	case *types.Pointer, *types.Interface:
		return true
	}

	return false
}

// namedPath is a named type's symbol id: the path of the package that declares it and its own name. A type the
// language itself declares, error among them, belongs to no package and is its bare name.
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

// qualify prints a package by its full path, so a type's text names the package it is declared in and two types
// of one name are never read as one.
func qualify(held *types.Package) string {
	if held == nil {
		return ""
	}

	return held.Path()
}

// symbolOf is the id of the declaration the object is, as CONTRACT.md spells Go's: a package-level declaration
// is its package's path and its own name, and a method or a field is that with the type it belongs to between
// them. A local variable is no declaration a call can reach, so it has no id.
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
			// A function declared inside another is reached by nobody outside it.
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

// receiverName is the name of the type a method is declared on, a pointer receiver read through to the type
// itself, since a method belongs to the type either way.
func receiverName(held types.Type) string {
	if pointer, isPointer := held.(*types.Pointer); isPointer {
		held = pointer.Elem()
	}
	if named, isNamed := held.(*types.Named); isNamed && named.Obj() != nil {
		return named.Obj().Name()
	}

	return ""
}
