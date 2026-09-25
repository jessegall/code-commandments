// Package backend holds the scribes that rewrite PHP, and the Writer they draft through.
package backend

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

// Writer drafts rewrites of one PHP file at the level a scribe thinks in: replace a node, stamp an attribute,
// wire an import, lift a line. The offset math is the engine's Source; a scribe never does its own.
type Writer struct {
	draft  *scribes.Draft
	path   string
	source []byte
	class  engine.Match
}

// For is the writer of the file a match sits in, drafting into draft.
func For(draft *scribes.Draft, match engine.Match) *Writer {
	source, _ := match.Source().Source()
	class := match
	if !match.Is(engine.TypeDeclaration) {
		class = match.EnclosingType()
	}

	return &Writer{draft: draft, path: match.File(), source: source, class: class}
}

// Source is the file's text, asked for offsets.
func (w *Writer) Source() engine.Source {
	return engine.Source(w.source)
}

// Replace replaces a node's whole span with text.
func (w *Writer) Replace(node engine.Match, text string) {
	start, end := bounds(node)
	w.edit(start, end, text)
}

// TextOf is the source a node spans, verbatim.
func (w *Writer) TextOf(node engine.Match) string {
	start, end := bounds(node)

	return string(w.source[start:end])
}

// InsertAt inserts text at an offset, consuming nothing.
func (w *Writer) InsertAt(offset int, text string) {
	w.edit(offset, offset, text)
}

// Rewrite drafts one edit with no node behind it.
func (w *Writer) Rewrite(edit scribes.Edit) {
	w.edit(edit.Start, edit.End, edit.Text)
}

// StampAttribute writes an attribute on a line of its own directly above a property, parameter or class, beneath
// any it already carries and at its indent, and imports the attribute when importFqcn names it.
func (w *Writer) StampAttribute(node engine.Match, attribute, importFqcn string) {
	insertAt, _ := bounds(node)
	if groups := node.ChildrenIn("attrGroups"); len(groups) > 0 {
		_, insertAt = bounds(groups[len(groups)-1])
	}
	keywordStart := w.Source().SkipWhitespace(insertAt, len(w.source))
	lead := string(w.source[insertAt:keywordStart])
	start, _ := bounds(node)
	indent := w.Source().IndentAt(start)
	w.edit(insertAt, keywordStart, lead+attribute+"\n"+indent)
	if importFqcn != "" {
		w.EnsureImport(importFqcn)
	}
}

// EnsureImport writes `use fqcn;` after the file's last import, or after its namespace declaration when it has
// none; nothing when the import is there already or the class is in no namespace.
func (w *Writer) EnsureImport(fqcn string) {
	namespace := w.namespace()
	if !namespace.Exists() {
		return
	}
	uses := importsIn(namespace)
	for _, use := range uses {
		for _, used := range use.ChildrenIn("uses") {
			if w.importedName(used) == fqcn {
				return
			}
		}
	}
	if len(uses) > 0 {
		_, end := bounds(uses[len(uses)-1])
		w.InsertAt(end, "\nuse "+fqcn+";")

		return
	}
	name := namespace.Child("name")
	if !name.Exists() {
		return
	}
	_, nameEnd := bounds(name)
	if semicolon, found := w.Source().After(nameEnd-1, ";"); found {
		w.InsertAt(semicolon+1, "\n\nuse "+fqcn+";")
	}
}

// DropImport takes `use fqcn;` out: the whole statement when it imports only that, else the one clause of a
// grouped import; nothing when the import is not there.
func (w *Writer) DropImport(fqcn string) {
	namespace := w.namespace()
	if !namespace.Exists() {
		return
	}
	for _, use := range importsIn(namespace) {
		clauses := use.ChildrenIn("uses")
		for index, used := range clauses {
			if w.importedName(used) != fqcn {
				continue
			}
			if len(clauses) == 1 {
				w.DeleteStatementLine(use)
			} else {
				w.dropClause(clauses, index)
			}

			return
		}
	}
}

// dropClause takes one clause out of a grouped import, with the comma joining it to the list.
func (w *Writer) dropClause(clauses []engine.Match, index int) {
	clauseStart, clauseEnd := bounds(clauses[index])
	start, end := clauseStart, clauseEnd
	if index > 0 {
		_, start = bounds(clauses[index-1])
	} else if comma, found := w.Source().After(clauseEnd-1, ","); found {
		end = comma + 1
	}
	w.edit(start, w.Source().SkipWhitespace(end, len(w.source)), "")
}

// DropModifier takes a modifier keyword out of a typed property's or parameter's modifiers.
func (w *Writer) DropModifier(node engine.Match, modifier string) {
	typed := node.Child("type")
	if !typed.Exists() {
		return
	}
	typeStart, _ := bounds(typed)
	modifiersStart, _ := bounds(node)
	if groups := node.ChildrenIn("attrGroups"); len(groups) > 0 {
		_, modifiersStart = bounds(groups[len(groups)-1])
	}
	keywordStart := w.Source().SkipWhitespace(modifiersStart, typeStart)
	modifiers := string(w.source[keywordStart:typeStart])
	w.edit(keywordStart, typeStart, strings.ReplaceAll(modifiers, modifier+" ", ""))
}

// RemoveReturnType takes a function's declared return type out with its colon; nothing when it declares none.
func (w *Writer) RemoveReturnType(function engine.Match) {
	returned := function.Child("returnType")
	if !returned.Exists() {
		return
	}
	start, end := bounds(returned)
	if colon, found := w.Source().Before(start, ":"); found {
		start = colon
	}
	w.edit(start, end, "")
}

// ReplaceDocblock replaces a declaration's docblock with text, the declaration untouched.
func (w *Writer) ReplaceDocblock(node engine.Match, text string) {
	if doc, documented := (php.Node{Match: node}).DocComment(); documented {
		w.edit(doc.Span.Start, doc.Span.End, text)
	}
}

// RemoveDocblock takes a declaration's docblock out, with its line when it stands alone on it.
func (w *Writer) RemoveDocblock(node engine.Match) {
	doc, documented := php.Node{Match: node}.DocComment()
	if !documented {
		return
	}
	after := doc.Span.End
	lineEnd := w.Source().LineEndAt(after)
	_, own := w.Source().OwnLineIndent(doc.Span.Start)
	alone := own && strings.Trim(string(w.source[after:lineEnd]), phpSpace) == ""
	if alone {
		w.edit(w.Source().LineStartAt(doc.Span.Start), lineEnd, "")

		return
	}
	w.edit(doc.Span.Start, after, "")
}

// ReplaceComments replaces a run of comments, the first through the last, with text.
func (w *Writer) ReplaceComments(comments []contract.Comment, text string) {
	if len(comments) == 0 {
		return
	}
	w.edit(comments[0].Span.Start, comments[len(comments)-1].Span.End, text)
}

// MoveBefore lifts declarations, in the order given, to just above anchor, each with its docblock, attributes and
// own line, a blank line between them: the moved text is the original bytes.
func (w *Writer) MoveBefore(nodes []engine.Match, anchor engine.Match) {
	var blocks []string
	for _, node := range nodes {
		start, end := w.declarationBounds(node, nodes)
		blocks = append(blocks, strings.Trim(string(w.source[start:end]), "\n"))
		w.edit(start, end, "")
	}
	if len(blocks) > 0 {
		w.InsertAt(w.lineStartOf(anchor), strings.Join(blocks, "\n\n")+"\n\n")
	}
}

// Reorder rewrites a run of declarations into groups of the same nodes, a blank line between groups and each
// member keeping the spacing it had inside one; one edit over the run, each declaration its original bytes.
func (w *Writer) Reorder(nodes []engine.Match, groups [][]engine.Match) {
	if len(nodes) == 0 {
		return
	}
	var pieces []string
	for _, group := range groups {
		for index, node := range group {
			start := w.lineStartOf(node)
			blank := len(pieces) > 0 && (index == 0 || w.hasBlankLineAbove(start))
			piece := string(w.source[start:w.declarationEndOf(node, nodes)])
			if blank {
				piece = "\n" + piece
			}
			pieces = append(pieces, piece)
		}
	}
	w.edit(w.lineStartOf(nodes[0]), w.declarationEndOf(nodes[len(nodes)-1], nodes), strings.Join(pieces, "\n"))
}

// DeleteStatementLine takes out the whole lines a statement stands on, its indentation through its line break.
func (w *Writer) DeleteStatementLine(statement engine.Match) {
	start, end := bounds(statement)
	lineStart := 0
	if newline, found := w.Source().Before(start, "\n"); found {
		lineStart = newline + 1
	}
	if end < len(w.source) && w.source[end] == '\n' {
		end++
	}
	w.edit(lineStart, end, "")
}

func (w *Writer) hasBlankLineAbove(lineStart int) bool {
	return lineStart >= 2 && w.source[lineStart-1] == '\n' && w.source[lineStart-2] == '\n'
}

// declarationBounds is where a declaration stands in the source: from the start of the line it opens on, the
// blank lines above taken with it, through the break that ends it.
func (w *Writer) declarationBounds(node engine.Match, run []engine.Match) (int, int) {
	start := w.lineStartOf(node)
	for w.hasBlankLineAbove(start) {
		start--
	}
	end := w.declarationEndOf(node, run)
	if end < len(w.source) && w.source[end] == '\n' {
		end++
	}

	return start, end
}

// declarationEndOf is where a declaration's text ends: through a comment trailing it on its line, unless another
// declaration of the run shares that line.
func (w *Writer) declarationEndOf(node engine.Match, run []engine.Match) int {
	_, end := bounds(node)
	lineEnd := w.Source().LineContentEndAt(end)
	for _, other := range run {
		if otherStart, _ := bounds(other); otherStart >= end && otherStart < lineEnd {
			return end
		}
	}

	return lineEnd
}

// lineStartOf is the offset the line a declaration opens on begins at: its docblock and the comments standing on
// their own lines above it included.
func (w *Writer) lineStartOf(node engine.Match) int {
	start, _ := bounds(node)
	for _, comment := range (php.Node{Match: node}).Comments() {
		if w.Source().StartsItsLine(comment.Span.Start) {
			start = min(start, comment.Span.Start)
		}
	}
	if newline, found := w.Source().Before(start, "\n"); found {
		return newline + 1
	}

	return 0
}

// namespace is the namespace statement the writer's class is declared in.
func (w *Writer) namespace() engine.Match {
	if parent := w.class.Parent(); parent.Kind() == "Stmt_Namespace" {
		return parent
	}

	return engine.Match{}
}

// importedName is the class a use clause imports, as written, its leading backslash dropped.
func (w *Writer) importedName(clause engine.Match) string {
	return strings.TrimLeft(w.TextOf(clause.Child("name")), `\`)
}

func (w *Writer) edit(start, end int, text string) {
	w.draft.Edit(engine.Span{Path: w.path, Source: w.source, Start: start, End: end}, text)
}

// importsIn is every use statement among a namespace's statements.
func importsIn(namespace engine.Match) []engine.Match {
	var uses []engine.Match
	for _, statement := range namespace.ChildrenIn("stmts") {
		if statement.Kind() == "Stmt_Use" {
			uses = append(uses, statement)
		}
	}

	return uses
}

// bounds is a node's half-open byte range.
func bounds(node engine.Match) (int, int) {
	span := node.Node().Span

	return span.Start, span.End
}

// phpSpace is the whitespace PHP's trim() strips.
const phpSpace = " \t\n\r\x00\x0b"
