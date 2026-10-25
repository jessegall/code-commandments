package gotree

import (
	"go/ast"
	"go/parser"
	"sort"
	"strings"
)

// comments are every comment group of the file, in source order, each told which node it belongs to.
//
// Go groups the lines of a comment itself, so one group is one comment here: the three `//` lines above a
// function are the function's one doc comment, which is how a reader of it wants them. A group's kind is doc
// where the parser gave it to a declaration as its documentation, which is the only thing that makes a `//` line
// a doc comment in Go.
func (w *writer) comments(parsed *ast.File) []comment {
	documents := documented(parsed)
	comments := []comment{}

	for _, group := range parsed.Comments {
		span := w.span(group)
		held := comment{
			ID:   len(comments),
			Kind: kindOfComment(group, documents[group]),
			Text: group.Text(),
			Span: span,
		}
		held.Text = w.text(group)
		if attached, trailing := w.attachment(group); attached != nil {
			held.Attached, held.Trailing = attached, trailing
		}
		if isGoCode(group) {
			held.Extras = &commentExtras{Go: &goComment{Code: true}}
		}
		comments = append(comments, held)
	}

	return comments
}

// text is the comment as written, markers and all, which is what the contract asks for: Group.Text strips them.
func (w *writer) text(group *ast.CommentGroup) string {
	span := w.span(group)
	if span[0] < 0 || span[1] > len(w.source) || span[0] >= span[1] {
		return group.Text()
	}

	return string(w.source[span[0]:span[1]])
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
	span := w.span(group)
	if trailing := w.endingBefore(span); trailing != nil {
		return trailing, true
	}

	return w.startingAfter(span), false
}

// startingAfter is the outermost node that starts at the first node position after the comment ends, which is
// what the comment leads. Nothing is skipped over in between, so a comment with a blank line and then code still
// leads that code, as Go's own documentation rules do not: the contract's rule is one for every language.
func (w *writer) startingAfter(span [3]int) *int {
	best := -1
	for _, held := range w.nodes {
		if held.Span[0] < span[1] {
			continue
		}
		if best < 0 || held.Span[0] < w.nodes[best].Span[0] || (held.Span[0] == w.nodes[best].Span[0] && held.ID < w.nodes[best].ID) {
			best = held.ID
		}
	}
	if best < 0 {
		return nil
	}

	return &w.nodes[best].ID
}

// endingBefore is the outermost node that ends on the comment's own line before it starts, which the comment
// trails. A comment on a line of its own trails nothing.
func (w *writer) endingBefore(span [3]int) *int {
	var ending []*node
	for _, held := range w.nodes {
		if held.Span[1] <= span[0] && w.lineOfEnd(held) == span[2] {
			ending = append(ending, held)
		}
	}
	if len(ending) == 0 {
		return nil
	}
	sort.SliceStable(ending, func(a, b int) bool {
		if ending[a].Span[1] != ending[b].Span[1] {
			return ending[a].Span[1] > ending[b].Span[1]
		}

		return ending[a].ID < ending[b].ID
	})

	return &ending[0].ID
}

// lineOfEnd is the line a node's last byte sits on.
func (w *writer) lineOfEnd(held *node) int {
	line := held.Span[2]
	for at := held.Span[0]; at < held.Span[1] && at < len(w.source); at++ {
		if w.source[at] == '\n' {
			line++
		}
	}

	return line
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
