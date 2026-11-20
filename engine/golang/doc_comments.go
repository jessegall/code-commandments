package golang

import (
	"go/doc/comment"
	"slices"
	"strings"
	"unicode"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

func commentReferences(held engine.Commented) []contract.Ref {
	module := held.File.File.Module
	if module == "" {
		return nil
	}
	parser := comment.Parser{LookupSym: func(receiver, name string) bool {
		first, _ := utf8.DecodeRuneInString(name)
		return receiver != "" || unicode.IsUpper(first)
	}}
	doc := parser.Parse(strings.Join(held.ProseLines(), "\n"))
	codebase := held.File.Codebase()
	var refs []contract.Ref
	for _, block := range doc.Content {
		paragraph, containsLinks := block.(*comment.Paragraph)
		if !containsLinks {
			continue
		}
		for _, part := range paragraph.Text {
			link, direct := part.(*comment.DocLink)
			if !direct || (link.ImportPath != "" && link.ImportPath != module) {
				continue
			}
			name := link.Name
			if link.Recv != "" {
				name = link.Recv + "." + name
			}
			owned := true
			ref := contract.Ref{Text: name, OwnedHere: &owned}
			if symbol := module + "." + name; len(codebase.Declarations(symbol)) > 0 {
				ref.Symbol = symbol
			}
			refs = append(refs, ref)
		}
	}

	return refs
}

var keywords = map[string]prose.Spelled{
	"IfStmt":     {Construct: prose.Condition},
	"ReturnStmt": {Construct: prose.Return},
	"RangeStmt":  {Construct: prose.Loop, Keywords: []string{"range", "over"}},
	"ForStmt":    {Construct: prose.ConditionalLoop, Keywords: []string{"for"}},
	"AssignStmt": {Construct: prose.Assignment},
	"FuncDecl":   {Construct: prose.Method},
	"TypeSpec":   {Construct: prose.Type},
}

func documentedSubject(node engine.Match) engine.Match {
	if node.Kind() == "GenDecl" {
		declarations := node.ChildrenIn("specs")
		if len(declarations) == 1 {
			return declarations[0]
		}
	}

	return node
}

func commentProse(comment engine.Commented) []string {
	return slices.DeleteFunc(comment.ProseLines(), func(line string) bool {
		return strings.HasPrefix(line, "Deprecated:") || strings.HasPrefix(line, "BUG(")
	})
}
