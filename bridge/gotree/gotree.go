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
)

// Name and Version name the bridge in a stream's header. The version is the Go release whose parser read the
// tree, because the shape of the tree is that parser's.
const Name = "go-bridge"

// Version is the Go release this binary was built with.
func Version() string {
	return strings.TrimPrefix(runtime.Version(), "go")
}

// contractVersion is the version of contract/CONTRACT.md this bridge writes.
const contractVersion = 3

// Write writes the stream for every Go file under the paths: a header, one line per file, and the trailer. The
// files in write are the ones the run reports on, and the rest are context, read so that a rule spanning files
// has what it needs.
//
// The files are grouped into packages and each package is type-checked once, because a type checker works in
// packages where a tree is read per file. A file that belongs to no package the checker could build, one no
// platform builds or one with no package clause to read, is still written: its tree is there and its types are
// not, which is the honest answer rather than a silent gap.
func Write(out io.Writer, paths, write []string) error {
	found := GoFiles(paths)
	judged := map[string]bool{}
	for _, path := range resolved(write) {
		judged[path] = true
	}
	context := func(path string) bool { return len(judged) > 0 && !judged[path] }

	lines := json.NewEncoder(out)
	header := map[string]any{"header": map[string]any{
		"contract": "tree", "version": contractVersion, "language": "go",
		"bridge": map[string]any{"name": Name, "version": Version()},
		"roots":  resolved(paths),
	}}
	if err := lines.Encode(header); err != nil {
		return err
	}

	fset := token.NewFileSet()
	checked := newChecker(fset, PackagesOf(found))
	written := map[string]bool{}
	count := 0

	for _, one := range PackagesOf(found) {
		_, facts := checked.check(one)
		for _, parsed := range checked.filesOf(one) {
			path := fset.Position(parsed.Pos()).Filename
			if written[path] {
				continue
			}
			written[path] = true
			line, err := fileLine(fset, parsed, path, one.Path, facts, context(path))
			if err != nil {
				return err
			}
			if err := lines.Encode(map[string]any{"file": line}); err != nil {
				return err
			}
			count++
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
			return fmt.Errorf("%s: %w", path, parseErr)
		}
		line, err := fileLine(fset, parsed, path, "", nil, context(path))
		if err != nil {
			return err
		}
		if err := lines.Encode(map[string]any{"file": line}); err != nil {
			return err
		}
		count++
	}

	return lines.Encode(map[string]any{"trailer": map[string]any{"files": count}})
}

// file is one source file's tree and comments, as the contract's file line.
type file struct {
	Path     string      `json:"path"`
	Language string      `json:"language"`
	Errors   int         `json:"errors"`
	Context  bool        `json:"context,omitempty"`
	Module   string      `json:"module,omitempty"`
	Resolver *resolver   `json:"resolver,omitempty"`
	Root     *node       `json:"root"`
	Comments []comment   `json:"comments"`
	Types    []*typeLine `json:"types,omitempty"`
}

// resolver says which resolver a file had and whether it ran on it, so a type that is absent always reads as
// "could not", never as "did not try".
type resolver struct {
	Tool string `json:"tool"`
	Ran  bool   `json:"ran"`
}

// typeLine is one type as the contract writes it: its canonical text, its shape, and what the checker knows
// about it. A file says each of its types once and its nodes name them by place.
type typeLine struct {
	Text       string      `json:"text"`
	Kind       string      `json:"kind"`
	Name       string      `json:"name,omitempty"`
	Args       []*typeLine `json:"args,omitempty"`
	Members    []*typeLine `json:"members,omitempty"`
	Fields     []typeField `json:"fields,omitempty"`
	Parameters []*typeLine `json:"parameters,omitempty"`
	Returns    *typeLine   `json:"returns,omitempty"`
	Nullable   bool        `json:"nullable,omitempty"`
	ValueType  bool        `json:"valueType,omitempty"`
	Element    *typeLine   `json:"element,omitempty"`
	Origin     string      `json:"origin"`
}

// typeField is one field of a struct type.
type typeField struct {
	Name string    `json:"name"`
	Type *typeLine `json:"type"`
}

// target is the declaration a call reaches.
type target struct {
	Symbol string `json:"symbol"`
	Type   string `json:"type,omitempty"`
	Name   string `json:"name"`
}

// node is one syntax node. Only the facts syntax alone gives are here; the types a checker gives are the
// bridge's second half, and no node carries a fact the engine fills.
type node struct {
	ID           int             `json:"id"`
	Kind         string          `json:"kind"`
	Role         string          `json:"role"`
	Is           []string        `json:"is,omitempty"`
	Span         [3]int          `json:"span"`
	Field        string          `json:"field,omitempty"`
	Children     []*node         `json:"children,omitempty"`
	Name         string          `json:"name,omitempty"`
	ResolvedType *int            `json:"resolvedType,omitempty"`
	Literal      string          `json:"literal,omitempty"`
	Value        json.RawMessage `json:"value,omitempty"`
	Operator     string          `json:"operator,omitempty"`
	Flags        []string        `json:"flags,omitempty"`
	Declared     *typeLine       `json:"declared,omitempty"`
	Returns      *typeLine       `json:"returns,omitempty"`
	Symbol       string          `json:"symbol,omitempty"`
	Refers       string          `json:"refers,omitempty"`
	Target       *target         `json:"target,omitempty"`
	Constant     bool            `json:"constant,omitempty"`
}

// comment is one comment group, which is what Go calls a run of lines with nothing but comment between them.
type comment struct {
	ID       int            `json:"id"`
	Kind     string         `json:"kind"`
	Text     string         `json:"text"`
	Span     [3]int         `json:"span"`
	Attached *int           `json:"attached,omitempty"`
	Trailing bool           `json:"trailing,omitempty"`
	Extras   *commentExtras `json:"extras,omitempty"`
}

// commentExtras are the facts only Go has about a comment.
type commentExtras struct {
	Go *goComment `json:"go,omitempty"`
}

// goComment holds whether the comment's words are code rather than prose, which is what a rule about a comment
// that only restates its statement reads.
type goComment struct {
	Code bool `json:"code,omitempty"`
}

// fileLine is one file's line: the tree the parser read, the comments, and the types the checker gave its
// nodes. A file the parser could only partly read is still written, with errors counting what it could not read,
// so a tree is never lost whole for a syntax error in it.
func fileLine(fset *token.FileSet, parsed *ast.File, path, module string, facts *types.Info, context bool) (*file, error) {
	source, err := read(path)
	if err != nil {
		return nil, err
	}

	tree := &writer{fset: fset, source: source, facts: facts, index: map[string]int{}, module: module}
	tree.owners = ownersIn(parsed, facts, module)
	root := tree.node(parsed, "", false)

	return &file{
		Path:     path,
		Language: "go",
		Errors:   errorsIn(parsed, source),
		Context:  context,
		Module:   module,
		Resolver: &resolver{Tool: "go/types", Ran: facts != nil},
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
	nodes  []*node
	// facts are what the checker learned about this file's very nodes; none where no package could be checked.
	facts *types.Info
	// module is the import path of the package the file belongs to, which a field's or a method's id starts with.
	module string
	// types are the distinct types the file's nodes resolve to, said once each, and index is where each one sits.
	types []*typeLine
	index map[string]int
	// owners is the id of the type that declares each field name the file writes, as the checker says it.
	owners map[*ast.Ident]string
}

// node is the node for one ast node: its own facts, then its children in source order, each told which field of
// this node it fills. inType says the node stands where a type is written, which Go's own tree does not say,
// since a type and an expression are the same nodes there.
func (w *writer) node(held ast.Node, field string, inType bool) *node {
	built := &node{ID: w.next, Kind: kindOf(held), Field: field, Span: w.span(held)}
	w.next++
	w.nodes = append(w.nodes, built)

	built.Role = roleOf(held, inType)
	built.Is = neutralOf(held)
	built.Name = nameOf(held)
	built.Operator = operatorOf(held)
	built.Flags = flagsOf(held)
	if literal, value := literalOf(held); literal != "" {
		built.Literal, built.Value = literal, value
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
func (w *writer) span(held ast.Node) [3]int {
	start := w.fset.Position(held.Pos())
	end := start
	if held.End().IsValid() {
		end = w.fset.Position(held.End())
	}

	return [3]int{start.Offset, max(start.Offset, end.Offset), start.Line}
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

// quoted is the JSON for a value a literal decodes to.
func quoted(value any) json.RawMessage {
	raw, err := json.Marshal(value)
	if err != nil {
		return nil
	}

	return raw
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
