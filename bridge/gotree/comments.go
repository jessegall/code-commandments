package gotree

import (
	"go/ast"
	"go/parser"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

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

func (w *writer) text(group *ast.CommentGroup) string {
	start, end, _ := w.span(group)
	if start < 0 || end > len(w.source) || start >= end {
		return group.Text()
	}

	return string(w.source[start:end])
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

func kindOfComment(group *ast.CommentGroup, documents bool) string {
	if documents {
		return "doc"
	}
	if len(group.List) > 0 && strings.HasPrefix(group.List[0].Text, "/*") {
		return "block"
	}

	return "line"
}

func (w *writer) attachment(group *ast.CommentGroup) (*int, bool) {
	start, end, line := w.span(group)
	if trailing := w.endingBefore(start, line); trailing != nil {
		return trailing, true
	}

	return w.startingAfter(end), false
}

func (w *writer) startingAfter(after int) *int {
	byStart := w.byStart()
	at := sort.Search(len(byStart), func(i int) bool { return byStart[i].Span.Start >= after })
	if at == len(byStart) {
		return nil
	}

	return &byStart[at].ID
}

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

func (w *writer) endingOn(line int) []int {
	if w.ending == nil {
		w.ending = map[int][]int{}
		for at, end := range w.ends {
			w.ending[end] = append(w.ending[end], at)
		}
	}

	return w.ending[line]
}

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

func isLoneName(statement ast.Stmt) bool {
	expression, isExpression := statement.(*ast.ExprStmt)
	if !isExpression {
		return false
	}
	_, isName := expression.X.(*ast.Ident)

	return isName
}
