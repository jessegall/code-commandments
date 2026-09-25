package python

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/prose"
)

var (
	// markerComment is a fixture marker: a comment that is nothing but one.
	markerComment = regexp.MustCompile(`^@(?:(?:sin|fixed|righteous)\s+\w+|example\s+\w+\s+(?:bad|good))$`)
	// commentLead is what opens a `#` comment's body: the marker, Sphinx's `#:`, and one space.
	commentLead = regexp.MustCompile(`^#:? ?`)
)

// commentWords is the words of a `#` comment, the marker and the space around them aside.
func commentWords(comment contract.Comment) string {
	return strings.TrimSpace(strings.TrimPrefix(comment.Text, "#"))
}

// commentBody is a `#` comment's body: what follows the marker, the indentation after it kept, so a run of
// comments nests one line under another.
func commentBody(comment contract.Comment) string {
	return strings.TrimRight(commentLead.ReplaceAllString(comment.Text, ""), " \t\r\n")
}

// isCodeComment says whether the comment reads as one Python statement, as the bridge judged it.
func isCodeComment(comment contract.Comment) bool {
	return comment.Extras != nil && comment.Extras.Python != nil && comment.Extras.Python.Code
}

// proseComments is the run of comments above the statement, fixture markers set aside.
func (n Node) proseComments() []contract.Comment {
	return slices.DeleteFunc(n.CommentsAbove(), func(comment contract.Comment) bool { return markerComment.MatchString(commentWords(comment)) })
}

// Prose is the prose written for the statement: the run of comments above it, as one text, and the docstring it
// opens with.
func (n Node) Prose() []string {
	var texts []string
	if comments := n.proseComments(); len(comments) > 0 {
		var bodies []string
		for _, comment := range comments {
			bodies = append(bodies, commentBody(comment))
		}
		texts = append(texts, strings.Join(bodies, "\n"))
	}
	if docstring, ok := n.Docstring(); ok {
		texts = append(texts, docstring)
	}

	return texts
}

// CommentWords is the content words of the comments above the statement; none when one of them is code.
func (n Node) CommentWords() []string {
	var words []string
	for _, comment := range n.proseComments() {
		if isCodeComment(comment) {
			return nil
		}
		words = append(words, commentWords(comment))
	}
	var unique []string
	for _, word := range prose.Words(strings.Join(words, " ")) {
		if !slices.Contains(unique, word) {
			unique = append(unique, word)
		}
	}

	return unique
}

// HasMultiParagraphDocstring says whether the class's docstring holds two or more paragraphs of prose.
func (n Node) HasMultiParagraphDocstring() bool {
	docstring, ok := n.Docstring()

	return n.Kind() == "ClassDef" && ok && ProseParagraphs(docstring) >= 2
}

// HasCeremonyDocstring says whether the def's docstring says nothing but what its signature already says.
func (n Node) HasCeremonyDocstring() bool {
	docstring, ok := n.Docstring()
	if !n.IsFunction() || !ok {
		return false
	}
	var annotated []string
	for _, parameter := range n.Parameters() {
		if parameter.Child("annotation").Exists() {
			annotated = append(annotated, parameter.Name())
		}
	}

	return OnlyRestates(docstring, annotated, n.Child("returns").Exists())
}

// HasDanglingDocReference says whether the statement's docstring cross-references a name inside a package the
// codebase owns that the codebase does not hold.
func (p *Program) HasDanglingDocReference(n Node) bool {
	docstring, ok := n.Docstring()

	return ok && slices.ContainsFunc(References(docstring), func(reference string) bool {
		return p.OwnsPackage(strings.Split(reference, ".")[0]) && !p.Resolves(reference)
	})
}

// IsNewlineJoin says whether the call joins with a newline: `"\n".join(...)`.
func (n Node) IsNewlineJoin() bool {
	callee := n.Callee()
	separator, ok := callee.Child("value").Text()

	return n.IsCall() && callee.Kind() == "Attribute" && callee.Name() == "join" && ok && separator == "\n"
}

// JoinedLines is the lines a join is handed as a list or tuple display.
func (n Node) JoinedLines() []Node {
	arguments := n.Arguments()
	if !n.IsCall() || len(arguments) == 0 || (arguments[0].Kind() != "List" && arguments[0].Kind() != "Tuple") {
		return nil
	}

	return arguments[0].ChildrenIn("elts")
}

// IsFixedText says whether the expression is text written out: a string literal or an f-string.
func (n Node) IsFixedText() bool {
	return n.Node().Literal == "string" || n.Kind() == "JoinedStr"
}

// RestatesCode says whether every content word of the comments above the statement is one the statement spells.
func (n Node) RestatesCode() bool {
	code := n.CodeWords()

	return !slices.ContainsFunc(n.CommentWords(), func(word string) bool { return !slices.Contains(code, word) })
}

// FixedLineCount is how many of a join's lines are text written out.
func (n Node) FixedLineCount() int {
	fixed := 0
	for _, line := range n.JoinedLines() {
		if line.IsFixedText() {
			fixed++
		}
	}

	return fixed
}
