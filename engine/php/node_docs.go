package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
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

// constructs is the construct each PHP node kind is, and the keywords PHP spells it with.
var constructs = map[string]struct {
	construct prose.Construct
	keywords  []string
}{
	"Stmt_Foreach": {prose.Loop, []string{"foreach"}}, "Stmt_For": {prose.Loop, []string{"for"}},
	"Stmt_While": {prose.ConditionalLoop, []string{"while"}}, "Stmt_Do": {prose.ConditionalLoop, []string{"do"}},
	"Stmt_If": {prose.Condition, []string{"if"}}, "Stmt_ElseIf": {prose.Condition, []string{"elseif"}},
	"Stmt_Else": {prose.Otherwise, []string{"else"}}, "Stmt_Return": {prose.Return, []string{"return"}},
	"Stmt_Break": {prose.Break, []string{"break"}}, "Stmt_Continue": {prose.Continue, []string{"continue"}},
	"Stmt_Switch": {prose.Branch, []string{"switch"}}, "Stmt_TryCatch": {prose.Attempt, []string{"try"}},
	"Stmt_Catch": {prose.Recovery, []string{"catch"}}, "Stmt_Throw": {prose.Failure, []string{"throw"}},
	"Stmt_Unset": {prose.Removal, []string{"unset"}}, "Stmt_Echo": {prose.Output, []string{"echo"}},
	"Stmt_Class": {prose.Type, []string{"class"}}, "Stmt_Interface": {prose.Contract, []string{"interface"}},
	"Stmt_Trait": {prose.Type, []string{"trait"}}, "Stmt_Enum": {prose.Type, []string{"enum"}},
	"Stmt_ClassMethod": {prose.Method, []string{"function"}}, "Stmt_Function": {prose.Method, []string{"function"}},
	"Stmt_Property": {prose.Field, nil}, "Stmt_ClassConst": {prose.Constant, []string{"const"}},
	"Expr_Throw": {prose.Failure, []string{"throw"}}, "Expr_Match": {prose.Branch, []string{"match"}},
	"Expr_New": {prose.Creation, []string{"new"}}, "Expr_Assign": {prose.Assignment, nil},
	"Expr_Ternary": {prose.Condition, nil},
}

// constructWords is the keywords and meanings of the construct a node kind is, none for any other.
func constructWords(kind string) []string {
	known, ok := constructs[kind]
	if !ok {
		return nil
	}

	return append(slices.Clone(known.keywords), known.construct.Words()...)
}
