// Package gotree writes the generic tree of contract/CONTRACT.md for Go sources. It is the one bridge that starts
// no process and needs no toolchain installed: the binary is written in Go, so go/ast is already in it.
//
// The tree is read from the parser by reflection over go/ast's own structs, which is what keeps the vocabulary the
// language's own: a node's kind is the name of its ast type, and a child's field is the name of the struct field
// it fills, lowercased. A bridge that switched on a hundred node types by hand would have to be taught every node
// the language gains; this one is told by the parser.
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

// Name and Version name the bridge in a stream's header. The version is the Go release whose parser read the
// tree, because the shape of the tree is that parser's.
const Name = "go-bridge"

// Version is the Go release this binary was built with.
func Version() string {
	return strings.TrimPrefix(runtime.Version(), "go")
}

// contractVersion is the version of contract/CONTRACT.md this bridge writes.
const contractVersion = 4

// Write writes the stream for every Go file under the paths: a header, one line per file, and the trailer. The
// files in write are the ones the run reports on, and the rest are context, read so that a rule spanning files
// has what it needs.
//
// The files are grouped into packages and each package is type-checked once, because a type checker works in
// packages where a tree is read per file. A file that belongs to no package the checker could build, one no
// platform builds or one with no package clause to read, is still written: its tree is there and its types are
// not, which is the honest answer rather than a silent gap.
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

// Stream is the tree of every Go file under the paths, held to the contract and ready for the engine, without
// ever being written out: this bridge runs in the same process as the engine, so the stream it builds is the
// stream the engine reads. Writing it as JSON and parsing it straight back costs most of what this bridge takes,
// and buys nothing a bridge over a pipe does not have to pay for.
//
// The files in write are the ones the run reports on, and the rest are context, read so that a rule spanning
// files has what it needs. A file that belongs to no package the checker could build, one this platform does not
// build or one with no package clause to read, is still there: its tree is written and its types are not, which
// is the honest answer rather than a silent gap.
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
	written := map[string]bool{}
	var files []*contract.File

	for _, one := range packages {
		_, facts := checked.check(one)
		for _, parsed := range checked.filesOf(one) {
			path := fset.Position(parsed.Pos()).Filename
			if written[path] {
				continue
			}
			written[path] = true
			file, err := fileLine(fset, parsed, path, one.Path, facts, context(path))
			if err != nil {
				return nil, err
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

// fileLine is one file's line: the tree the parser read, the comments, and the types the checker gave its
// nodes. A file the parser could only partly read is still written, with errors counting what it could not read,
// so a tree is never lost whole for a syntax error in it.
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

// writer holds what every node of one file is read against: the positions the parser keeps, the file's bytes, and
// the numbers handed out in the order a pre-order walk meets the nodes.
type writer struct {
	fset   *token.FileSet
	source []byte
	next   int
	nodes  []*contract.Node
	// ends is the line each node's last byte sits on, by the node's own number, read from the parser's positions
	// rather than counted again: a comment is attached by the line a node ends on, and counting the bytes of
	// every node for every comment is what made a file of ten thousand nodes take seconds.
	ends []int
	// facts are what the checker learned about this file's very nodes; none where no package could be checked.
	facts *types.Info
	// module is the import path of the package the file belongs to, which a field's or a method's id starts with.
	module string
	// types are the distinct types the file's nodes resolve to, said once each, and index is where each one sits.
	types []*contract.Type
	index map[string]int
	// owners is the id of the type that declares each field name the file writes, as the checker says it.
	owners map[*ast.Ident]string
	// started is the file's nodes by where they start, and ending the numbers of those ending on each line: both
	// are built once, the first time a comment asks what it belongs to.
	started []*contract.Node
	ending  map[int][]int
}

// node is the node for one ast node: its own facts, then its children in source order, each told which field of
// this node it fills. inType says the node stands where a type is written, which Go's own tree does not say,
// since a type and an expression are the same nodes there.
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

// slots are a node's children in source order, read from go/ast's own struct fields. A field holding a comment is
// no child, since the contract keeps comments on the file rather than in the tree, and a field that repeats nodes
// the tree already holds is none either.
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

// nodesIn are the ast nodes a struct field holds: one for a field that is a node, each item for a list of them,
// and none for anything else.
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

// skippedField names the fields that are not a node's children. ast.File keeps a second list of the names it
// could not resolve and the file's comment groups; both are already read elsewhere, and reading them again would
// write the same node twice.
func skippedField(held ast.Node, field string) bool {
	if _, isFile := held.(*ast.File); isFile {
		return field == "Unresolved" || field == "Comments" || field == "Scope"
	}

	return field == "Doc" || field == "Comment"
}

// isComment says whether the node is a comment, which the contract keeps on the file and not in the tree.
func isComment(held ast.Node) bool {
	switch held.(type) {
	case *ast.Comment, *ast.CommentGroup:
		return true
	}

	return false
}

// span is the node's bytes and the line it starts on, as the contract's [start, end, line].
//
// A node the parser recovered from a syntax error can end before it starts, or nowhere at all: a function whose
// body never opened has no closing brace to end at. Such a node ends where it begins, so its span is empty and
// no reader is handed bytes the node does not stand for.
func (w *writer) span(held ast.Node) (int, int, int) {
	start := w.fset.Position(held.Pos())
	end := start
	if held.End().IsValid() {
		end = w.fset.Position(held.End())
	}

	return start.Offset, max(start.Offset, end.Offset), start.Line
}

// endLine is the line the node's last byte sits on, as the parser knows it.
func (w *writer) endLine(held ast.Node) int {
	if !held.End().IsValid() {
		return w.fset.Position(held.Pos()).Line
	}

	return w.fset.Position(held.End()).Line
}

// kindOf is the name Go's own parser gives the node's type, which is the vocabulary the contract asks for.
func kindOf(held ast.Node) string {
	name := reflect.TypeOf(held).String()

	return strings.TrimPrefix(strings.TrimPrefix(name, "*"), "ast.")
}

// lowerFirst is a struct field's name as a contract field: Body is body, Rhs is rhs, X is x.
func lowerFirst(name string) string {
	if name == "" {
		return ""
	}

	return strings.ToLower(name[:1]) + name[1:]
}

// errorsIn counts what the parser could not read in the file, by parsing it once more for its errors alone: the
// tree a run writes comes from the checker's own parse, which keeps no error list of its own.
func errorsIn(parsed *ast.File, source []byte) int {
	if _, err := parser.ParseFile(token.NewFileSet(), "errors.go", source, parser.SkipObjectResolution); err != nil {
		if list, isList := err.(interface{ Len() int }); isList {
			return list.Len()
		}

		return 1
	}

	return 0
}

// quoted is the value a literal decodes to, as the contract carries one.
func quoted(value any) *contract.Value {
	held, err := contract.ValueOf(value)
	if err != nil {
		return nil
	}

	return held
}

// decimal is an integer literal as a decimal string, whatever base it was written in, so no width is lost.
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
