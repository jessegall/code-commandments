package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/prose"
)

// parserComments is every node's comments as php-parser attaches them: a comment belongs to the outermost node that
// starts at the next code after it, one that trails code on its line included.
var parserComments = Memoised(func(codebase *engine.Codebase) map[*contract.Node][]contract.Comment {
	owned := map[*contract.Node][]contract.Comment{}
	for _, file := range codebase.Files() {
		starts := map[int]*contract.Node{}
		for _, node := range file.Nodes() {
			if _, taken := starts[node.Span.Start]; !taken && node.ID != 0 {
				starts[node.Span.Start] = node
			}
		}
		source, _ := file.Source()
		for _, comment := range file.File.Comments {
			if owner := commentOwner(file, comment, starts, source); owner != nil {
				owned[owner] = append(owned[owner], comment)
			}
		}
	}

	return owned
})

func commentOwner(file *engine.File, comment contract.Comment, starts map[int]*contract.Node, source []byte) *contract.Node {
	if !comment.Trailing {
		if comment.Attached == nil {
			return nil
		}
		owner, _ := file.File.Node(*comment.Attached)

		return owner
	}
	ends := map[int]int{}
	for _, other := range file.File.Comments {
		ends[other.Span.Start] = other.Span.End
	}
	next := comment.Span.End
	for {
		for next < len(source) && strings.IndexByte(" \t\r\n", source[next]) >= 0 {
			next++
		}
		end, isComment := ends[next]
		if !isComment {
			break
		}
		next = end
	}

	return starts[next]
}

// Comments is every comment php-parser attaches to the node, in source order.
func (n Node) Comments() []contract.Comment {
	if !n.Exists() {
		return nil
	}

	return parserComments.Of(n.Codebase())[n.Node()]
}

// DocComment is the last doc comment attached to the node, as php-parser's getDocComment reads it.
func (n Node) DocComment() (contract.Comment, bool) {
	comments := n.Comments()
	for at := len(comments) - 1; at >= 0; at-- {
		if comments[at].Kind == "doc" {
			return comments[at], true
		}
	}

	return contract.Comment{}, false
}

// HasDocComment says whether a doc comment is attached to the node.
func (n Node) HasDocComment() bool {
	_, ok := n.DocComment()

	return ok
}

// HasLineComment says whether a comment other than a doc comment is attached to the node.
func (n Node) HasLineComment() bool {
	for _, comment := range n.Comments() {
		if comment.Kind != "doc" {
			return true
		}
	}

	return false
}

// HasCommentMatching says whether a comment attached to the node says what the check looks for.
func (n Node) HasCommentMatching(says func(string) bool) bool {
	for _, comment := range n.Comments() {
		if says(comment.Text) {
			return true
		}
	}

	return false
}

// Docblocks is every doc comment attached to the node, stacked ones included.
func (n Node) Docblocks() []contract.Comment {
	var blocks []contract.Comment
	for _, comment := range n.Comments() {
		if strings.HasPrefix(comment.Text, "/**") {
			blocks = append(blocks, comment)
		}
	}

	return blocks
}

// LineComments is the text of every // or # comment attached to the node.
func (n Node) LineComments() []contract.Comment {
	var lines []contract.Comment
	for _, comment := range n.Comments() {
		if comment.Kind != "doc" && (strings.HasPrefix(comment.Text, "//") || strings.HasPrefix(comment.Text, "#")) {
			lines = append(lines, comment)
		}
	}

	return lines
}

// HasStackedDocblocks says whether more than one doc comment is attached to the node.
func (n Node) HasStackedDocblocks() bool {
	return len(n.Docblocks()) > 1
}

// HasInlineDocblock says whether the node's doc comment opens or closes on a content line.
func (n Node) HasInlineDocblock() bool {
	doc, ok := n.DocComment()

	return ok && DocblockIsInline(doc.Text)
}

// HasMultiParagraphDocblock says whether the node is a class-like whose doc comment runs to two or more prose
// paragraphs.
func (n Node) HasMultiParagraphDocblock() bool {
	doc, ok := n.DocComment()

	return n.IsClassLike() && ok && DocParagraphs(doc.Text) >= 2
}

// DocReferences is every class the node's doc comment points at with {@see} or {@link}.
func (n Node) DocReferences() []string {
	doc, ok := n.DocComment()
	if !ok {
		return nil
	}

	return DocReferences(doc.Text)
}

// HasCommentedOutCode says whether a line comment attached to the node is code left commented out.
func (n Node) HasCommentedOutCode() bool {
	for _, comment := range n.LineComments() {
		if comment.Extras != nil && comment.Extras.PHP != nil && comment.Extras.PHP.Code {
			return true
		}
	}

	return false
}

// IsClassLike says whether the node declares a class, interface, trait or enum.
func (n Node) IsClassLike() bool {
	return isClassLike(n.Match)
}

// IsFunctionDeclaration says whether the node declares a method or a function.
func (n Node) IsFunctionDeclaration() bool {
	return n.Kind() == "Stmt_ClassMethod" || n.Kind() == "Stmt_Function"
}

// IsArrayItem says whether the node is an array item or sits directly in one.
func (n Node) IsArrayItem() bool {
	return n.Kind() == "ArrayItem" || n.Parent().Kind() == "ArrayItem"
}

// HasCeremonyDocblock says whether the node is a function whose doc comment only restates its native types: tags
// alone, every @param naming a typed parameter's own type with nothing to add, and at least one of them.
func (n Node) HasCeremonyDocblock() bool {
	doc, ok := n.DocComment()
	if !n.IsFunctionDeclaration() || !ok {
		return false
	}
	native := map[string]string{}
	for _, param := range Params(n.Match) {
		variable := param.Child("var")
		if rendered := typeKeyOf(Written(param.Node().Declared)); rendered != "" && variable.Kind() == "Expr_Variable" && variable.Name() != "" {
			native[variable.Name()] = rendered
		}
	}
	nativeReturn := typeKeyOf(Written(n.Node().Returns))
	restatements := 0
	for _, line := range prose.Lines(doc.Text) {
		line = prose.Trim(strings.TrimLeft(prose.Trim(line), "/*"))
		if line == "" {
			continue
		}
		if !strings.HasPrefix(line, "@") {
			return false
		}
		if match := paramTag.FindStringSubmatch(line); match != nil {
			if prose.Trim(match[3]) != "" || native[match[2]] == "" || typeKey(match[1]) != native[match[2]] {
				return false
			}
			restatements++

			continue
		}
		if match := returnTag.FindStringSubmatch(line); match != nil {
			if prose.Trim(match[2]) != "" || nativeReturn == "" || typeKey(match[1]) != nativeReturn {
				return false
			}

			continue
		}

		return false
	}

	return restatements >= 1
}

// CodeWords is every word the node's own code says, keywords of its constructs included; a nested statement's
// words are its own.
func (n Node) CodeWords() []string {
	var words []string
	seen := map[string]bool{}
	var harvest func(node engine.Match, root bool)
	harvest = func(node engine.Match, root bool) {
		if !root && strings.HasPrefix(node.Kind(), "Stmt_") {
			return
		}
		for _, word := range constructWords(node.Kind()) {
			if stem := prose.Stem(word); !seen[stem] {
				seen[stem] = true
				words = append(words, stem)
			}
		}
		for _, word := range prose.Words(spelled(node)) {
			if !seen[word] {
				seen[word] = true
				words = append(words, word)
			}
		}
		for _, child := range node.Children() {
			harvest(child, false)
		}
	}
	if n.Exists() {
		harvest(n.Match, true)
	}

	return words
}

// spelled is the text a node writes out: a name, a variable's name, a string's value.
func spelled(node engine.Match) string {
	switch {
	case node.Kind() == "Identifier" || node.Kind() == "VarLikeIdentifier" || isName(node):
		return node.Name()
	case node.Kind() == "Expr_Variable":
		return node.Name()
	case node.Kind() == "Scalar_String":
		text, _ := node.Text()

		return text
	}

	return ""
}

var (
	paramTag  = regexp.MustCompile(`^@param\s+(\S+)\s+\$(\w+)\s*(.*)$`)
	returnTag = regexp.MustCompile(`^@return\s+(\S+)\s*(.*)$`)
)

func isClassLike(node engine.Match) bool {
	return slices.Contains(classLikes, node.Kind())
}

// typeKeyOf is a declared type's comparable spelling; empty when nothing is declared.
func typeKeyOf(declared TypeName) string {
	rendered := declared.Render()
	if rendered == "" {
		return ""
	}

	return typeKey(rendered)
}

// typeKey is a type's spelling as a docblock and a declaration compare equal: lower case, no leading ? or \, null
// dropped from a union, a union's members sorted.
func typeKey(written string) string {
	key := strings.ToLower(strings.TrimLeft(written, `?\`))
	key = strings.ReplaceAll(strings.ReplaceAll(key, "|null", ""), "null|", "")
	if !strings.Contains(key, "|") {
		return key
	}
	var members []string
	for _, member := range strings.Split(key, "|") {
		if member = strings.TrimLeft(member, `\`); member != "" {
			members = append(members, member)
		}
	}
	slices.Sort(members)

	return strings.Join(members, "|")
}

// constructKeywords are the words a construct is spelled with, and the words that name what it does.
var constructKeywords = map[string][]string{
	"Stmt_Foreach": {"foreach"}, "Stmt_For": {"for"}, "Stmt_While": {"while"}, "Stmt_Do": {"do"},
	"Stmt_If": {"if"}, "Stmt_ElseIf": {"elseif"}, "Stmt_Else": {"else"}, "Stmt_Return": {"return"},
	"Stmt_Break": {"break"}, "Stmt_Continue": {"continue"}, "Stmt_Switch": {"switch"}, "Stmt_TryCatch": {"try"},
	"Stmt_Catch": {"catch"}, "Stmt_Throw": {"throw"}, "Stmt_Unset": {"unset"}, "Stmt_Echo": {"echo"},
	"Stmt_Class": {"class"}, "Stmt_Interface": {"interface"}, "Stmt_Trait": {"trait"}, "Stmt_Enum": {"enum"},
	"Stmt_ClassMethod": {"function"}, "Stmt_Function": {"function"}, "Stmt_Property": {}, "Stmt_ClassConst": {"const"},
	"Expr_Throw": {"throw"}, "Expr_Match": {"match"}, "Expr_New": {"new"}, "Expr_Assign": {}, "Expr_Ternary": {},
}

// constructMeanings are the words that name what each construct does.
var constructMeanings = map[string][]string{
	"Stmt_Foreach": meansLoop, "Stmt_For": meansLoop, "Stmt_While": meansConditionalLoop, "Stmt_Do": meansConditionalLoop,
	"Stmt_If": meansCondition, "Stmt_ElseIf": meansCondition, "Stmt_Else": {"else", "otherwise"},
	"Stmt_Return": {"return", "give", "yield", "result"}, "Stmt_Break": {"stop", "leave"},
	"Stmt_Continue": {"skip", "next"}, "Stmt_Switch": meansBranch, "Stmt_TryCatch": {"try", "catch", "handle"},
	"Stmt_Catch": {"catch", "handle", "error"}, "Stmt_Throw": meansFailure, "Stmt_Unset": {"remove", "drop", "clear"},
	"Stmt_Echo": {"print", "output"}, "Stmt_Class": meansTypeWords, "Stmt_Interface": {"interface", "contract"},
	"Stmt_Trait": meansTypeWords, "Stmt_Enum": meansTypeWords, "Stmt_ClassMethod": meansMethod, "Stmt_Function": meansMethod,
	"Stmt_Property": {"property", "field"}, "Stmt_ClassConst": {"const", "constant"}, "Expr_Throw": meansFailure,
	"Expr_Match": meansBranch, "Expr_New": {"new", "create", "make", "build"}, "Expr_Assign": {"set", "assign", "store"},
	"Expr_Ternary": meansCondition,
}

var (
	meansLoop            = []string{"loop", "iterate", "every", "each"}
	meansConditionalLoop = []string{"loop", "until", "repeat"}
	meansCondition       = []string{"if", "when", "check", "whether", "otherwise"}
	meansBranch          = []string{"match", "case", "branch"}
	meansFailure         = []string{"throw", "raise", "fail", "error"}
	meansTypeWords       = []string{"class", "type"}
	meansMethod          = []string{"method", "function"}
)

// constructWords is the keywords and meanings of the construct a node kind is, none for any other.
func constructWords(kind string) []string {
	keywords, ok := constructKeywords[kind]
	if !ok {
		return nil
	}

	return append(slices.Clone(keywords), constructMeanings[kind]...)
}
