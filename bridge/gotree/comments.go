package gotree

import (
	"go/ast"
	"go/doc/comment"
	"go/parser"
	"go/scanner"
	"go/token"
	"go/types"
	"strings"
	"unicode"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/contract"
)

// comments lists every comment group of the file: a leading one attached to the outermost node starting at the first
// token after it, unless a blank line comes between, a trailing one to the outermost node ending on its line before it.
func (w *writer) comments(parsed *ast.File, nodes []*contract.Node) []contract.Comment {
	documents := documented(parsed)
	owners := make([]*contract.Node, 0, len(nodes))
	for _, node := range nodes {
		if node.Kind != "file" && node.Kind != "module" && node.Kind != "block" {
			owners = append(owners, node)
		}
	}
	ends := map[int]int{}
	for _, group := range parsed.Comments {
		ends[w.fset.Position(group.Pos()).Offset] = w.fset.Position(group.End()).Offset
	}
	grouped := firstOfEachGroup(w.fset, parsed)
	comments := []contract.Comment{}

	for _, group := range parsed.Comments {
		start, end := w.fset.Position(group.Pos()), w.fset.Position(group.End())
		held := contract.Comment{
			ID:   len(comments),
			Form: formOf(group, documents[group]),
			Text: string(w.source[start.Offset:end.Offset]),
			Span: contract.Span{Start: start.Offset, End: end.Offset, Line: start.Line},
		}
		lineStart := strings.LastIndexByte(string(w.source[:start.Offset]), '\n') + 1
		if group == parsed.Doc {
			held.Attached = moduleNode(nodes)
		} else if strings.TrimSpace(string(w.source[lineStart:start.Offset])) != "" {
			held.Trailing = true
			for _, node := range owners {
				if node.Span.End <= start.Offset && node.Span.End > lineStart {
					held.Attached = &node.ID
					break
				}
			}
		} else if next, leads := leading(w.source, end.Offset, ends); leads {
			if first, isGroup := grouped[next]; isGroup {
				next = first
			}
			for _, node := range owners {
				if node.Span.Start == next && node.Span.End > node.Span.Start {
					held.Attached = &node.ID
					break
				}
			}
		}
		if held.Form != "doc" {
			held.Fragment = fragment(group)
		} else {
			held.Description, held.Refs = w.described(group)
		}
		comments = append(comments, held)
	}

	return comments
}

func moduleNode(nodes []*contract.Node) *int {
	for _, node := range nodes {
		if node.Kind == "module" {
			return &node.ID
		}
	}

	return nil
}

func firstOfEachGroup(fset *token.FileSet, parsed *ast.File) map[int]int {
	first := map[int]int{}
	for _, declared := range parsed.Decls {
		if general, isGeneral := declared.(*ast.GenDecl); isGeneral && general.Lparen.IsValid() && len(general.Specs) > 0 {
			first[fset.Position(general.Pos()).Offset] = fset.Position(general.Specs[0].Pos()).Offset
		}
	}

	return first
}

// leading is where the code a comment leads starts, other comments skipped; none past a blank line.
func leading(source []byte, at int, ends map[int]int) (int, bool) {
	for {
		breaks := 0
		for at < len(source) && strings.ContainsRune(" \t\r\n", rune(source[at])) {
			if source[at] == '\n' {
				breaks++
			}
			at++
		}
		if breaks > 1 {
			return 0, false
		}
		skipped, isComment := ends[at]
		if !isComment {
			return at, true
		}
		at = skipped
	}
}

func documented(parsed *ast.File) map[*ast.CommentGroup]bool {
	documents := map[*ast.CommentGroup]bool{parsed.Doc: true}
	ast.Inspect(parsed, func(held ast.Node) bool {
		switch node := held.(type) {
		case *ast.FuncDecl:
			documents[node.Doc] = true
		case *ast.GenDecl:
			documents[node.Doc] = true
		case *ast.TypeSpec:
			documents[node.Doc] = true
		case *ast.ValueSpec:
			documents[node.Doc] = true
		case *ast.ImportSpec:
			documents[node.Doc] = true
		case *ast.Field:
			documents[node.Doc] = true
		}

		return true
	})
	delete(documents, nil)

	return documents
}

func formOf(group *ast.CommentGroup, documents bool) string {
	if documents {
		return "doc"
	}
	if len(group.List) > 0 && strings.HasPrefix(group.List[0].Text, "/*") {
		return "block"
	}

	return "line"
}

// fragment is what Go's parser makes of a comment's words read as statements; the shared rule decides whether they
// are code. A lone expression's kind is what the translation makes of it, as for any expression of the file.
func fragment(group *ast.CommentGroup) *contract.Fragment {
	words := strings.TrimSpace(group.Text())
	read := &contract.Fragment{Context: "statement"}
	if words == "" {
		return read
	}
	source := "func() {\n" + words + "\n}"
	fset := token.NewFileSet()
	parsed, err := parser.ParseExprFrom(fset, "", source, 0)
	if list, isList := err.(scanner.ErrorList); isList {
		read.Diagnostics = list.Len()
	} else if err != nil {
		read.Diagnostics = 1
	}
	body, isFunc := parsed.(*ast.FuncLit)
	if !isFunc || body.Body == nil {
		return read
	}
	read.Statements = len(body.Body.List)
	if len(body.Body.List) == 1 {
		if statement, isExpression := body.Body.List[0].(*ast.ExprStmt); isExpression {
			words := &writer{fset: fset, source: []byte(source), types: map[types.Type]*contract.Type{}, imported: map[string]bool{}}
			read.ExpressionKind = words.expression(statement.X).Kind
		}
	}

	return read
}

// described is what a doc comment says in prose, its Deprecated: and BUG( lines left out, and the doc links it makes
// into its own module, each resolved through the package scope when it names a declaration.
func (w *writer) described(group *ast.CommentGroup) ([]string, []contract.Ref) {
	text := group.Text()
	var description []string
	for _, line := range strings.Split(strings.TrimRight(text, "\n"), "\n") {
		if !strings.HasPrefix(line, "Deprecated:") && !strings.HasPrefix(line, "BUG(") {
			description = append(description, strings.TrimSpace(line))
		}
	}
	parser := comment.Parser{LookupSym: func(receiver, name string) bool {
		first, _ := utf8.DecodeRuneInString(name)
		return receiver != "" || unicode.IsUpper(first)
	}}
	var refs []contract.Ref
	for _, block := range parser.Parse(text).Content {
		paragraph, links := block.(*comment.Paragraph)
		if !links {
			continue
		}
		for _, part := range paragraph.Text {
			link, direct := part.(*comment.DocLink)
			if !direct || (link.ImportPath != "" && link.ImportPath != w.module) {
				continue
			}
			name, owned := link.Name, true
			if link.Recv != "" {
				name = link.Recv + "." + name
			}
			refs = append(refs, contract.Ref{Text: name, Symbol: w.linked(link.Recv, link.Name), OwnedHere: &owned})
		}
	}

	return description, refs
}

// linked is the symbol a doc link names in the file's own package, or nothing when the package declares no such name.
func (w *writer) linked(receiver, name string) string {
	if w.facts == nil {
		return ""
	}
	var scope *types.Package
	for _, object := range w.facts.Defs {
		if object != nil && object.Pkg() != nil {
			scope = object.Pkg()
			break
		}
	}
	if scope == nil {
		return ""
	}
	if receiver == "" {
		return symbolOf(scope.Scope().Lookup(name))
	}
	owner, isType := scope.Scope().Lookup(receiver).(*types.TypeName)
	if !isType {
		return ""
	}
	member, _, _ := types.LookupFieldOrMethod(owner.Type(), true, scope, name)

	return symbolOf(member)
}
