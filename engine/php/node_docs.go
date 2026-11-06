package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

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

func (n Node) Comments() []contract.Comment {
	if !n.Exists() {
		return nil
	}

	return parserComments.Of(n.Codebase())[n.Node()]
}

func (n Node) DocComment() (contract.Comment, bool) {
	comments := n.Comments()
	for at := len(comments) - 1; at >= 0; at-- {
		if comments[at].Kind == "doc" {
			return comments[at], true
		}
	}

	return contract.Comment{}, false
}

func (n Node) HasDocComment() bool {
	_, ok := n.DocComment()

	return ok
}

func (n Node) HasLineComment() bool {
	for _, comment := range n.Comments() {
		if comment.Kind != "doc" {
			return true
		}
	}

	return false
}

func (n Node) HasCommentMatching(says func(string) bool) bool {
	for _, comment := range n.Comments() {
		if says(comment.Text) {
			return true
		}
	}

	return false
}

func (n Node) Docblocks() []contract.Comment {
	var blocks []contract.Comment
	for _, comment := range n.Comments() {
		if strings.HasPrefix(comment.Text, "/**") {
			blocks = append(blocks, comment)
		}
	}

	return blocks
}

func (n Node) LineComments() []contract.Comment {
	var lines []contract.Comment
	for _, comment := range n.Comments() {
		if comment.Kind != "doc" && (strings.HasPrefix(comment.Text, "//") || strings.HasPrefix(comment.Text, "#")) {
			lines = append(lines, comment)
		}
	}

	return lines
}

func (n Node) HasStackedDocblocks() bool {
	return len(n.Docblocks()) > 1
}

func (n Node) HasInlineDocblock() bool {
	doc, ok := n.DocComment()

	return ok && DocblockIsInline(doc.Text)
}

func (n Node) HasMultiParagraphDocblock() bool {
	doc, ok := n.DocComment()

	return n.IsClassLike() && ok && DocParagraphs(doc.Text) >= 2
}

func (n Node) DocReferences() []string {
	doc, ok := n.DocComment()
	if !ok {
		return nil
	}

	return DocReferences(doc.Text)
}

func (n Node) HasCommentedOutCode() bool {
	for _, comment := range n.LineComments() {
		if comment.Extras != nil && comment.Extras.PHP != nil && comment.Extras.PHP.Code {
			return true
		}
	}

	return false
}

func (n Node) IsClassLike() bool {
	return isClassLike(n.Match)
}

func (n Node) IsFunctionDeclaration() bool {
	return n.Kind() == "Stmt_ClassMethod" || n.Kind() == "Stmt_Function"
}

func (n Node) IsArrayItem() bool {
	return n.Kind() == "ArrayItem" || n.Parent().Kind() == "ArrayItem"
}

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

func (n Node) CodeWords() []string {
	var words []string
	seen := map[string]bool{}
	keep := func(word string) {
		if seen[word] {
			return
		}
		seen[word] = true
		words = append(words, word)
	}
	var harvest func(node engine.Match, root bool)
	harvest = func(node engine.Match, root bool) {
		if !root && strings.HasPrefix(node.Kind(), "Stmt_") {
			return
		}
		for _, word := range constructWords(node.Kind()) {
			keep(prose.Stem(word))
		}
		for _, word := range prose.Words(spelled(node)) {
			keep(word)
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

func typeKeyOf(declared TypeName) string {
	rendered := declared.Render()
	if rendered == "" {
		return ""
	}

	return typeKey(rendered)
}

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

func constructWords(kind string) []string {
	known, ok := constructs[kind]
	if !ok {
		return nil
	}

	return append(slices.Clone(known.keywords), known.construct.Words()...)
}
