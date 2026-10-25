package gotree

import (
	"go/ast"
	"go/parser"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// comments are every comment group of the file, in source order, each told which node it belongs to.
//
// Go groups the lines of a comment itself, so one group is one comment here: the three `//` lines above a
// function are the function's one doc comment, which is how a reader of it wants them. A group's kind is doc
// where the parser gave it to a declaration as its documentation, which is the only thing that makes a `//` line
// a doc comment in Go.
func (w *writer) comments(parsed *ast.File) []contract.Comment {
	documents := documented(parsed)
	comments := []contract.Comment{}

	for _, group := range parsed.Comments {
		start, end, line := w.span(group)
		held := contract.Comment{
			ID:   len(comments),
			Kind: kindOfComment(group, documents[group]),
			Text: w.text(group),
			Span: contract.Span{Start: start, End: end, Line: line},
		}
		if attached, trailing := w.attachment(group); attached != nil {
			held.Attached, held.Trailing = attached, trailing
		}
		if isGoCode(group) {
			held.Extras = &contract.CommentExtras{Go: &contract.GoComment{Code: true}}
		}
		comments = append(comments, held)
	}

	return comments
}

// text is the comment as written, markers and all, which is what the contract asks for: Group.Text strips them.
func (w *writer) text(group *ast.CommentGroup) string {
	start, end, _ := w.span(group)
	if start < 0 || end > len(w.source) || start >= end {
		return group.Text()
	}

	return string(w.source[start:end])
}

// documented are the comment groups the parser handed to a declaration as its documentation.
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

// kindOfComment is the comment's kind in the contract's terms: a block comment is written /* */, a group a
// declaration is documented by is doc, and anything else is a line comment.
func kindOfComment(group *ast.CommentGroup, documents bool) string {
	if documents {
		return "doc"
	}
	if len(group.List) > 0 && strings.HasPrefix(group.List[0].Text, "/*") {
		return "block"
	}

	return "line"
}

// attachment is the node a comment belongs to, and whether it trails it, as the contract's rule says: a leading
// comment belongs to the outermost node that starts at the first token after it, and a trailing one to the
// outermost node that ends on its own line before it. A comment with code on neither side belongs to nothing.
func (w *writer) attachment(group *ast.CommentGroup) (*int, bool) {
	start, end, line := w.span(group)
	if trailing := w.endingBefore(start, line); trailing != nil {
		return trailing, true
	}

	return w.startingAfter(end), false
}

// startingAfter is the outermost node that starts at the first node position after the comment ends, which is
// what the comment leads. Nothing is skipped over in between, so a comment with a blank line and then code still
// leads that code, as Go's own documentation rules do not: the contract's rule is one for every language.
//
// The nodes are read through an index sorted once per file, since a file holds thousands of nodes and hundreds of
// comments and walking all of the first for each of the second is what made a large file take seconds.
func (w *writer) startingAfter(after int) *int {
	byStart := w.byStart()
	at := sort.Search(len(byStart), func(i int) bool { return byStart[i].Span.Start >= after })
	if at == len(byStart) {
		return nil
	}

	return &byStart[at].ID
}

// byStart is the file's nodes by where they start, the outermost first where two start together, built once.
func (w *writer) byStart() []*contract.Node {
	if w.started != nil {
		return w.started
	}
	w.started = append([]*contract.Node(nil), w.nodes...)
	sort.SliceStable(w.started, func(a, b int) bool {
		if w.started[a].Span.Start != w.started[b].Span.Start {
			return w.started[a].Span.Start < w.started[b].Span.Start
		}

		return w.started[a].ID < w.started[b].ID
	})

	return w.started
}

// endingBefore is the outermost node that ends on the comment's own line before it starts, which the comment
// trails. A comment on a line of its own trails nothing.
func (w *writer) endingBefore(before, line int) *int {
	best := -1
	for _, at := range w.endingOn(line) {
		held := w.nodes[at]
		if held.Span.End > before {
			continue
		}
		if best < 0 || held.Span.End > w.nodes[best].Span.End || (held.Span.End == w.nodes[best].Span.End && held.ID < w.nodes[best].ID) {
			best = held.ID
		}
	}
	if best < 0 {
		return nil
	}

	return &w.nodes[best].ID
}

// endingOn are the numbers of the nodes whose last byte sits on that line, grouped once per file.
func (w *writer) endingOn(line int) []int {
	if w.ending == nil {
		w.ending = map[int][]int{}
		for at, end := range w.ends {
			w.ending[end] = append(w.ending[end], at)
		}
	}

	return w.ending[line]
}

// isGoCode says whether the comment's words are one Go statement from end to end rather than prose, which is what
// a rule about a comment restating its own statement reads. Prose parses as nothing, or as several statements.
func isGoCode(group *ast.CommentGroup) bool {
	words := strings.TrimSpace(group.Text())
	if words == "" || strings.Contains(words, "\n") {
		return false
	}
	parsed, err := parser.ParseExpr("func() { " + words + " }")
	if err != nil {
		return false
	}
	body, isFunc := parsed.(*ast.FuncLit)

	return isFunc && len(body.Body.List) == 1 && !isLoneName(body.Body.List[0])
}

// isLoneName says whether the statement is a single name, which is a label in prose and not code.
func isLoneName(statement ast.Stmt) bool {
	expression, isExpression := statement.(*ast.ExprStmt)
	if !isExpression {
		return false
	}
	_, isName := expression.X.(*ast.Ident)

	return isName
}
