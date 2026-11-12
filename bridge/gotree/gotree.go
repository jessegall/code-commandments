// Package gotree writes the generic tree of contract/CONTRACT.md for Go sources.
package gotree

import (
	"encoding/json"
	"fmt"
	"go/ast"
	"go/parser"
	"go/token"
	"go/types"
	"io"
	"reflect"
	"runtime"
	"sort"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

const Name = "go-bridge"

func Version() string {
	return strings.TrimPrefix(runtime.Version(), "go")
}

const contractVersion = 4

func Write(out io.Writer, paths, write []string) error {
	stream, err := Stream(paths, write)
	if err != nil {
		return err
	}

	lines := json.NewEncoder(out)
	if err := lines.Encode(map[string]any{"header": stream.Header}); err != nil {
		return err
	}
	for _, file := range stream.Files {
		if err := lines.Encode(map[string]any{"file": file}); err != nil {
			return err
		}
	}

	return lines.Encode(map[string]any{"trailer": stream.Trailer})
}

func Stream(paths, write []string) (*contract.Stream, error) {
	found := GoFiles(paths)
	judged := map[string]bool{}
	for _, path := range resolved(write) {
		judged[path] = true
	}
	context := func(path string) bool { return len(judged) > 0 && !judged[path] }

	fset := token.NewFileSet()
	packages := PackagesOf(found)
	checked := newChecker(fset, packages)
	kept, keeps := keptLinesFor(found, packages)
	written := map[string]bool{}
	var files []*contract.File

	for _, one := range packages {
		if keeps {
			if held, all := kept.allOf(one, context); all {
				for _, file := range held {
					written[file.Path] = true
				}
				files = append(files, held...)

				continue
			}
		}

		_, facts := checked.check(one)
		for _, parsed := range checked.filesOf(one) {
			path := fset.Position(parsed.Pos()).Filename
			if written[path] {
				continue
			}
			written[path] = true

			key := ""
			if keeps {
				key = kept.keyOf(path, one.Path)
				if held, read := kept.read(key, path, context(path)); read {
					files = append(files, held)

					continue
				}
			}

			file, err := fileLine(fset, parsed, path, one.Path, facts, context(path))
			if err != nil {
				return nil, err
			}
			if key != "" {
				kept.keep(key, file)
			}
			files = append(files, file)
		}
	}

	for _, path := range found {
		if written[path] {
			continue
		}
		source, err := read(path)
		if err != nil {
			continue
		}
		parsed, parseErr := parser.ParseFile(fset, path, source, parser.ParseComments|parser.SkipObjectResolution)
		if parsed == nil {
			return nil, fmt.Errorf("%s: %w", path, parseErr)
		}
		file, err := fileLine(fset, parsed, path, "", nil, context(path))
		if err != nil {
			return nil, err
		}
		files = append(files, file)
	}

	header := contract.Header{
		Contract: "tree",
		Version:  contractVersion,
		Language: contract.Go,
		Bridge:   contract.Bridge{Name: Name, Version: Version()},
		Roots:    resolved(paths),
	}

	return contract.Accept(header, files, nil, contract.Trailer{Files: len(files)})
}

func fileLine(fset *token.FileSet, parsed *ast.File, path, module string, facts *types.Info, context bool) (*contract.File, error) {
	source, err := read(path)
	if err != nil {
		return nil, err
	}

	tree := &writer{fset: fset, source: source, facts: facts, index: map[string]int{}, module: module}
	tree.owners = ownersIn(parsed, facts, module)
	root := tree.node(parsed, "", false)

	return &contract.File{
		Path:     path,
		Language: contract.Go,
		Errors:   errorsIn(parsed, source),
		Context:  context,
		Module:   module,
		Resolver: &contract.Resolver{Tool: "go/types", Ran: facts != nil},
		Root:     root,
		Comments: tree.comments(parsed),
		Types:    tree.types,
	}, nil
}

// writer holds what every node of one file is read against: the positions the parser keeps, the file's bytes, and the numbers handed out in the order a pre-order walk meets the nodes.
type writer struct {
	fset    *token.FileSet
	source  []byte
	next    int
	nodes   []*contract.Node
	ends    []int
	facts   *types.Info
	module  string
	types   []*contract.Type
	index   map[string]int
	owners  map[*ast.Ident]string
	started []*contract.Node
	ending  map[int][]int
}

func (w *writer) node(held ast.Node, field string, inType bool) *contract.Node {
	start, end, line := w.span(held)
	built := contract.NewNode(contract.Node{
		ID:    w.next,
		Kind:  kindOf(held),
		Role:  roleOf(held, inType),
		Is:    neutralOf(held),
		Span:  contract.Span{Start: start, End: end, Line: line},
		Field: field,
		Name:  nameOf(held),
	})
	w.next++
	w.nodes = append(w.nodes, built)
	w.ends = append(w.ends, w.endLine(held))

	if operator := operatorOf(held); operator != "" {
		built.Own().Operator = operator
	}
	if flags := flagsOf(held); len(flags) > 0 {
		built.Own().Flags = flags
	}
	if literal, value := literalOf(held); literal != "" {
		built.Own().Literal = literal
		built.Own().Value = value
	}
	w.typed(held, built)

	for _, slot := range w.slots(held) {
		built.Children = append(built.Children, w.node(slot.held, slot.field, inType || isTypeField(slot.field)))
	}

	return built
}

// slot is one child and the field of its parent it fills.
type slot struct {
	held  ast.Node
	field string
}

func (w *writer) slots(held ast.Node) []slot {
	value := reflect.ValueOf(held)
	if value.Kind() == reflect.Pointer {
		if value.IsNil() {
			return nil
		}
		value = value.Elem()
	}
	if value.Kind() != reflect.Struct {
		return nil
	}

	var slots []slot
	for i := range value.NumField() {
		field := value.Type().Field(i)
		if !field.IsExported() || skippedField(held, field.Name) {
			continue
		}
		name := lowerFirst(field.Name)
		for _, child := range nodesIn(value.Field(i)) {
			slots = append(slots, slot{held: child, field: name})
		}
	}
	sort.SliceStable(slots, func(a, b int) bool { return slots[a].held.Pos() < slots[b].held.Pos() })

	return slots
}

func nodesIn(value reflect.Value) []ast.Node {
	switch value.Kind() {
	case reflect.Interface, reflect.Pointer:
		if value.IsNil() {
			return nil
		}
		held, isNode := value.Interface().(ast.Node)
		if !isNode || isComment(held) {
			return nil
		}

		return []ast.Node{held}
	case reflect.Slice:
		var nodes []ast.Node
		for i := range value.Len() {
			nodes = append(nodes, nodesIn(value.Index(i))...)
		}

		return nodes
	}

	return nil
}

func skippedField(held ast.Node, field string) bool {
	if _, isFile := held.(*ast.File); isFile {
		return field == "Unresolved" || field == "Comments" || field == "Scope"
	}

	return field == "Doc" || field == "Comment"
}

func isComment(held ast.Node) bool {
	switch held.(type) {
	case *ast.Comment, *ast.CommentGroup:
		return true
	}

	return false
}

func (w *writer) span(held ast.Node) (int, int, int) {
	start := w.fset.Position(held.Pos())
	end := start
	if held.End().IsValid() {
		end = w.fset.Position(held.End())
	}

	return start.Offset, max(start.Offset, end.Offset), start.Line
}

func (w *writer) endLine(held ast.Node) int {
	if !held.End().IsValid() {
		return w.fset.Position(held.Pos()).Line
	}

	return w.fset.Position(held.End()).Line
}

func kindOf(held ast.Node) string {
	name := reflect.TypeOf(held).String()

	return strings.TrimPrefix(strings.TrimPrefix(name, "*"), "ast.")
}

func lowerFirst(name string) string {
	if name == "" {
		return ""
	}

	return strings.ToLower(name[:1]) + name[1:]
}

func errorsIn(parsed *ast.File, source []byte) int {
	if _, err := parser.ParseFile(token.NewFileSet(), "errors.go", source, parser.SkipObjectResolution); err != nil {
		if list, isList := err.(interface{ Len() int }); isList {
			return list.Len()
		}

		return 1
	}

	return 0
}

func quoted(value any) *contract.Value {
	held, err := contract.ValueOf(value)
	if err != nil {
		return nil
	}

	return held
}

func decimal(text string) string {
	cleaned := strings.ReplaceAll(text, "_", "")
	if number, err := strconv.ParseInt(cleaned, 0, 64); err == nil {
		return strconv.FormatInt(number, 10)
	}
	if number, err := strconv.ParseUint(cleaned, 0, 64); err == nil {
		return strconv.FormatUint(number, 10)
	}

	return text
}
