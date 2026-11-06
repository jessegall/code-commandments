package python

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/prose"
)

var commentLead = regexp.MustCompile(`^#:? ?`)

func commentWords(comment contract.Comment) string {
	return strings.TrimSpace(strings.TrimPrefix(comment.Text, "#"))
}

func commentBody(comment contract.Comment) string {
	return strings.TrimRight(commentLead.ReplaceAllString(comment.Text, ""), " \t\r\n")
}

func isCodeComment(comment contract.Comment) bool {
	return comment.Extras != nil && comment.Extras.Python != nil && comment.Extras.Python.Code
}

func (n Node) proseComments() []contract.Comment {
	return slices.DeleteFunc(n.CommentsAbove(), func(comment contract.Comment) bool { return prose.IsFixtureMarker(commentWords(comment)) })
}

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

func (n Node) HasMultiParagraphDocstring() bool {
	docstring, ok := n.Docstring()

	return n.Kind() == "ClassDef" && ok && ProseParagraphs(docstring) >= 2
}

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

func (p *Program) HasDanglingDocReference(n Node) bool {
	docstring, ok := n.Docstring()

	return ok && slices.ContainsFunc(References(docstring), func(reference string) bool {
		return p.OwnsPackage(strings.Split(reference, ".")[0]) && !p.Resolves(reference)
	})
}

func (n Node) IsNewlineJoin() bool {
	callee := n.Callee()
	separator, ok := callee.Child("value").Text()

	return n.IsCall() && callee.Kind() == "Attribute" && callee.Name() == "join" && ok && separator == "\n"
}

func (n Node) JoinedLines() []Node {
	arguments := n.Arguments()
	if !n.IsCall() || len(arguments) == 0 || (arguments[0].Kind() != "List" && arguments[0].Kind() != "Tuple") {
		return nil
	}

	return arguments[0].ChildrenIn("elts")
}

func (n Node) IsFixedText() bool {
	return n.Node().Literal == "string" || n.Kind() == "JoinedStr"
}

func (n Node) RestatesCode() bool {
	code := n.CodeWords()

	return !slices.ContainsFunc(n.CommentWords(), func(word string) bool { return !slices.Contains(code, word) })
}

func (n Node) FixedLineCount() int {
	fixed := 0
	for _, line := range n.JoinedLines() {
		if line.IsFixedText() {
			fixed++
		}
	}

	return fixed
}
